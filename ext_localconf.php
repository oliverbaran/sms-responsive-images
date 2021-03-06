<?php
defined('TYPO3_MODE') or die();

call_user_func(function () {
    // Check if demo plugin should be enabled
    $extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['sms_responsive_images']);
    if (empty($extConf['enableDemoPlugin'])) {
        return;
    }

    // Enable demo plugin
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
        'SMS.sms_responsive_images',
        'ResponsiveImages',
        ['Media' => 'header'],
        ['Media' => 'header']
    );
});

// Make sms a global namespace
if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['sms'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['sms'] = [];
}
$GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['sms'][] = 'SMS\\SmsResponsiveImages\\ViewHelpers';
