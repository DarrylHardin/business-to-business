(function($){

    if (typeof Craft.BusinessToBusiness === 'undefined') {
        Craft.BusinessToBusiness = {};
    }
    
    var elementTypeClass = 'importantcoding\\businesstobusiness\\elements\\Voucher';
    
    Craft.BusinessToBusiness.VoucherIndex = Craft.BaseElementIndex.extend({
        editableBusinesses: null,
        $newVoucherBtnBusiness: null,
        $newVoucherBtn: null,
    
        init: function(elementType, $container, settings) {
            this.on('selectSource', $.proxy(this, 'updateButton'));
            this.on('selectSite', $.proxy(this, 'updateButton'));
            this.base(elementType, $container, settings);
        },
    
        afterInit: function() {
            // Find which of the visible businesses the user has permission to create new vouchers in
            this.editableBusinesses = [];
    
            for (var i = 0; i < Craft.BusinessToBusiness.editableBusinesses.length; i++) {
                var business = Craft.BusinessToBusiness.editableBusinesses[i];
    
                if (this.getSourceByKey('business:' + business.id)) {
                    this.editableBusinesses.push(business);
                }
            }
    
            this.base();
        },
    
        getDefaultSourceKey: function() {
            // Did they request a specific business in the URL?
            if (this.settings.context === 'index' && typeof defaultBusinessHandle !== 'undefined') {
                for (var i = 0; i < this.$sources.length; i++) {
                    var $source = $(this.$sources[i]);
                    
                    if ($source.data('handle') === defaultBusinessHandle) {
                        return $source.data('key');
                    }
                }
            }
    
            return this.base();
        },
    
        updateButton: function() {
            if (!this.$source) {
                return;
            }
    
            // Get the handle of the selected source
            var selectedSourceHandle = this.$source.data('handle');
    
            var i, href, label;
    
            // Update the New Voucher button
            // ---------------------------------------------------------------------
    
            if (this.editableBusinesses.length) {
                // Remove the old button, if there is one
                if (this.$newVoucherBtnBusiness) {
                    this.$newVoucherBtnBusiness.remove();
                }
    
                // Determine if they are viewing a business that they have permission to create vouchers in
                var selectedBusiness;
    
                if (selectedSourceHandle) {
                    for (i = 0; i < this.editableBusinesses.length; i++) {
                        if (this.editableBusinesses[i].handle === selectedSourceHandle) {
                            selectedBusiness = this.editableBusinesses[i];
                            break;
                        }
                    }
                }
    
                this.$newVoucherBtnBusiness = $('<div class="btngroup submit"/>');
                var $menuBtn;
    
                // If they are, show a primary "New voucher" button, and a dropdown of the other voucher types (if any).
                // Otherwise only show a menu button
                if (selectedBusiness) {
                    href = this._getBusinessTriggerHref(selectedBusiness);
                    label = (this.settings.context === 'index' ? Craft.t('business-to-business', 'New voucher') : Craft.t('business-to-business', 'New {business} voucher', { business: selectedBusiness.name }));
                    this.$newVoucherBtn = $('<a class="btn submit add icon" ' + href + '>' + Craft.escapeHtml(label) + '</a>').appendTo(this.$newVoucherBtnBusiness);
    
                    if (this.settings.context !== 'index') {
                        this.addListener(this.$newVoucherBtn, 'click', function(ev) {
                            this._openCreateVoucherModal(ev.currentTarget.getAttribute('data-id'));
                        });
                    }
    
                    if (this.editableBusinesses.length > 1) {
                        $menuBtn = $('<div class="btn submit menubtn"></div>').appendTo(this.$newVoucherBtnBusiness);
                    }
                } else {
                    this.$newVoucherBtn = $menuBtn = $('<div class="btn submit add icon menubtn">' + Craft.t('business-to-business', 'New voucher') + '</div>').appendTo(this.$newVoucherBtnBusiness);
                }
    
                if ($menuBtn) {
                    var menuHtml = '<div class="menu"><ul>';
    
                    for (var i = 0; i < this.editableBusinesses.length; i++) {
                        var business = this.editableBusinesses[i];
    
                        if (this.settings.context === 'index' || business !== selectedBusiness) {
                            href = this._getBusinessTriggerHref(business);
                            label = (this.settings.context === 'index' ? business.name : Craft.t('business-to-business', 'New {business} voucher', { business: business.name }));
                            menuHtml += '<li><a ' + href + '">' + Craft.escapeHtml(label) + '</a></li>';
                        }
                    }
    
                    menuHtml += '</ul></div>';
    
                    $(menuHtml).appendTo(this.$newVoucherBtnBusiness);
                    var menuBtn = new Garnish.MenuBtn($menuBtn);
    
                    if (this.settings.context !== 'index') {
                        menuBtn.on('optionSelect', $.proxy(function(ev) {
                            this._openCreateVoucherModal(ev.option.getAttribute('data-id'));
                        }, this));
                    }
                }
    
                this.addButton(this.$newVoucherBtnBusiness);
            }
    
            // Update the URL if we're on the Vouchers index
            // ---------------------------------------------------------------------
    
            if (this.settings.context === 'index' && typeof history !== 'undefined') {
                var uri = 'business-to-business/vouchers';
    
                if (selectedSourceHandle) {
                    uri += '/'+selectedSourceHandle;
                }
    
                history.replaceState({}, '', Craft.getUrl(uri));
            }
        },
    
        _getBusinessTriggerHref: function(business) {
            if (this.settings.context === 'index') {
                var uri = 'business-to-business/vouchers/' + business.handle + '/new';
                
                if (this.siteId && this.siteId != Craft.primarySiteId) {
                    for (var i = 0; i < Craft.sites.length; i++) {
                        if (Craft.sites[i].id == this.siteId) {
                            uri += '/' + Craft.sites[i].handle;
                        }
                    }
                }
    
                return 'href="' + Craft.getUrl(uri) + '"';
            } else {
                return 'data-id="' + business.id + '"';
            }
        },
    
        _openCreateVoucherModal: function(businessId) {
            if (this.$newVoucherBtn.hasClass('loading')) {
                return;
            }
    
            // Find the business
            var business;
    
            for (var i = 0; i < this.editableBusinesses.length; i++) {
                if (this.editableBusinesses[i].id === businessId) {
                    business = this.editableBusinesses[i];
                    break;
                }
            }
    
            if (!business) {
                return;
            }
    
            this.$newVoucherBtn.addClass('inactive');
            var newVoucherBtnText = this.$newVoucherBtn.text();
            this.$newVoucherBtn.text(Craft.t('business-to-business', 'New {business} voucher', { business: business.name }));
    
            new Craft.ElementEditor({
                hudTrigger: this.$newVoucherBtnGroup,
                elementType: elementTypeClass,
                siteId: this.siteId,
                attributes: {
                    businessId: businessId,
                },
                onBeginLoading: $.proxy(function() {
                    this.$newVoucherBtn.addClass('loading');
                }, this),
                onEndLoading: $.proxy(function() {
                    this.$newVoucherBtn.removeClass('loading');
                }, this),
                onHideHud: $.proxy(function() {
                    this.$newVoucherBtn.removeClass('inactive').text(newVoucherBtnText);
                }, this),
                onSaveElement: $.proxy(function(response) {
                    // Make sure the right business is selected
                    var businessSourceKey = 'business:' + businessId;
    
                    if (this.sourceKey !== businessSourceKey) {
                        this.selectSourceByKey(businessSourceKey);
                    }
    
                    this.selectElementAfterUpdate(response.id);
                    this.updateElements();
                }, this)
            });
        }
    });
    
    // Register it!
    Craft.registerElementIndexClass(elementTypeClass, Craft.BusinessToBusiness.VoucherIndex);
    
    })(jQuery);
    