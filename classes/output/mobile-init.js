const that = this;

class AddonLocalMailLinkHandler extends that.CoreContentLinksHandlerBase {
    name = 'AddonLocalMailLinkHandler';
    pattern = new RegExp('/local/mail/view.php');

    getActions(siteIds, url, params) {
        const action = {
            action(siteId) {
                const page = `siteplugins/content/local_mail/view/0`;
                const pageParams = {
                    title: 'plugin.local_mail.pluginname',
                    args: params,
                };
                that.CoreNavigatorService.navigateToSitePath(page, { params: pageParams, siteId });
            },
        };

        return [action];
    }
}

that.CoreContentLinksDelegate.registerHandler(new AddonLocalMailLinkHandler());
