<?php
/**
 * Default Acl plugin.  Custom Acl plugin should override this value.
 */
Configure::write('Site.acl_plugin', 'Acl');

/**
 * Admin theme
 */
//Configure::write('Site.admin_theme', 'sample');

/**
 * Cache configuration
 */
$cacheConfig = array(
	'duration' => '+1 hour',
	'path' => CACHE . 'queries',
	'engine' => Configure::read('Cache.defaultEngine'),
);

// models
Cache::config('setting_write_configuration', $cacheConfig);

// components
Cache::config('croogo_blocks', $cacheConfig);
Cache::config('croogo_menus', $cacheConfig);
Cache::config('croogo_nodes', $cacheConfig);
Cache::config('croogo_types', $cacheConfig);
Cache::config('croogo_vocabularies', $cacheConfig);

// controllers
Cache::config('nodes_view', $cacheConfig);
Cache::config('nodes_promoted', $cacheConfig);
Cache::config('nodes_term', $cacheConfig);
Cache::config('nodes_index', $cacheConfig);
Cache::config('contacts_view', $cacheConfig);

/**
 * Failed login attempts
 *
 * Default is 5 failed login attempts in every 5 minutes
 */
$failedLoginDuration = 300;
Configure::write('User.failed_login_limit', 5);
Configure::write('User.failed_login_duration', $failedLoginDuration);
Cache::config('users_login', array_merge($cacheConfig, array(
	'duration' => '+' . $failedLoginDuration . ' seconds',
)));

/**
 * Settings
 */
App::uses('CroogoJsonReader', 'Configure');
Configure::config('settings', new CroogoJsonReader());
if (file_exists(APP . 'Config' . DS . 'settings.json')) {
	Configure::load('settings', 'settings');
}

/**
 * Locale
 */
Configure::write('Config.language', Configure::read('Site.locale'));

/**
 * Setup custom paths
 */
if ($theme = Configure::read('Site.theme')) {
	App::build(array(
		'View/Helper' => array(App::themePath($theme) . 'Helper' . DS),
	));
}

/**
 * List of core plugins
 */

Configure::write('Core.corePlugins', array(
	'Settings', 'Acl', 'Blocks', 'Comments', 'Contacts', 'Menus', 'Meta',
	'Nodes', 'Taxonomy', 'Users',
));

/**
 * Plugins
 */
$aclPlugin = Configure::read('Site.acl_plugin');
$pluginBootstraps = Configure::read('Hook.bootstraps');
$plugins = array_filter(explode(',', $pluginBootstraps));

// Re-order plugins based on denpendencies:
// for e.g, Tinymce depends on Wysiwyg
// if in Hook.bootstraps Tinymce appears before Wysiwyg, 
// we will reorder it so that it loads right after Wysiwyg
$pluginsOrdered = $plugins;
foreach ($plugins as $p) {
	$jsonPath = APP . 'Plugin' . DS . $p . DS . 'Config' . DS . 'plugin.json';
	if (file_exists($jsonPath)) {
		$pluginData = json_decode(file_get_contents($jsonPath), true);
		if (isset($pluginData['dependencies']) && isset($pluginData['dependencies']['plugins'])) {
			foreach ($pluginData['dependencies']['plugins'] as $d) {
				$k = array_search($p, $pluginsOrdered);
				$dk = array_search($d, $pluginsOrdered);
				if ($dk > $k) {
					unset($pluginsOrdered[$k]);
					$pluginsOrdered = array_slice($pluginsOrdered, 0, $k + 1, true) + 
						array($p => $p) + 
						array_slice($pluginsOrdered, $k + 1, count($pluginsOrdered) - 1, true);
					$pluginsOrdered = array_values($pluginsOrdered);
				}
			}
		}
	}
}
$plugins = $pluginsOrdered;

if (!in_array($aclPlugin, $plugins)) {
	$plugins = Hash::merge((array)$aclPlugin, $plugins);
}
foreach ($plugins as $plugin) {
	$pluginName = Inflector::camelize($plugin);
	if (!file_exists(APP . 'Plugin' . DS . $pluginName)) {
		CakeLog::write(LOG_ERR, 'Plugin not found during bootstrap: ' . $pluginName);
		continue;
	}
	$bootstrapFile = APP . 'Plugin' . DS . $pluginName . DS . 'Config' . DS . 'bootstrap.php';
	$bootstrap = file_exists($bootstrapFile);
	$routesFile = APP . 'Plugin' . DS . $pluginName . DS . 'Config' . DS . 'routes.php';
	$routes = file_exists($routesFile);
	$option = array(
		$pluginName => array(
			'bootstrap' => $bootstrap,
			'routes' => $routes,
		)
	);
	CroogoPlugin::load($option);
}
CroogoEventManager::loadListeners();
