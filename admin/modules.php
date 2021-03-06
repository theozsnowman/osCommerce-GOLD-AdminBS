<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2015 osCommerce

  Released under the GNU General Public License
*/

  require('includes/application_top.php');

  $set = (isset($HTTP_GET_VARS['set']) ? $HTTP_GET_VARS['set'] : '');

  $modules = $cfgModules->getAll();

  if (empty($set) || !$cfgModules->exists($set)) {
    $set = $modules[0]['code'];
  }

  $module_type = $cfgModules->get($set, 'code');
  $module_directory = $cfgModules->get($set, 'directory');
  $module_language_directory = $cfgModules->get($set, 'language_directory');
  $module_key = $cfgModules->get($set, 'key');;
  define('HEADING_TITLE', $cfgModules->get($set, 'title'));
  $template_integration = $cfgModules->get($set, 'template_integration');

  $action = (isset($HTTP_GET_VARS['action']) ? $HTTP_GET_VARS['action'] : '');

  if (tep_not_null($action)) {
    switch ($action) {
      case 'save':
        reset($HTTP_POST_VARS['configuration']);
        while (list($key, $value) = each($HTTP_POST_VARS['configuration'])) {
          tep_db_query("update " . TABLE_CONFIGURATION . " set configuration_value = '" . $value . "' where configuration_key = '" . $key . "'");
        }
        tep_redirect(tep_href_link(FILENAME_MODULES, 'set=' . $set . '&module=' . $HTTP_GET_VARS['module']));
        break;
      case 'install':
      case 'remove':
        $file_extension = substr($PHP_SELF, strrpos($PHP_SELF, '.'));
        $class = basename($HTTP_GET_VARS['module']);
        if (file_exists($module_directory . $class . $file_extension)) {
          include($module_directory . $class . $file_extension);
          $module = new $class;
          if ($action == 'install') {
            $module->install();

            $modules_installed = explode(';', constant($module_key));
            $modules_installed[] = $class . $file_extension;
            tep_db_query("update " . TABLE_CONFIGURATION . " set configuration_value = '" . implode(';', $modules_installed) . "' where configuration_key = '" . $module_key . "'");
            tep_redirect(tep_href_link(FILENAME_MODULES, 'set=' . $set . '&module=' . $class));
          } elseif ($action == 'remove') {
            $module->remove();

            $modules_installed = explode(';', constant($module_key));
            unset($modules_installed[array_search($class . $file_extension, $modules_installed)]);
            tep_db_query("update " . TABLE_CONFIGURATION . " set configuration_value = '" . implode(';', $modules_installed) . "' where configuration_key = '" . $module_key . "'");
            tep_redirect(tep_href_link(FILENAME_MODULES, 'set=' . $set));
          }
        }
        tep_redirect(tep_href_link(FILENAME_MODULES, 'set=' . $set . '&module=' . $class));
        break;
    }
  }

  require(DIR_WS_INCLUDES . 'template_top.php');

  $modules_installed = (defined($module_key) ? explode(';', constant($module_key)) : array());
  $new_modules_counter = 0;

  $file_extension = substr($PHP_SELF, strrpos($PHP_SELF, '.'));
  $directory_array = array();
  if ($dir = @dir($module_directory)) {
    while ($file = $dir->read()) {
      if (!is_dir($module_directory . $file)) {
        if (substr($file, strrpos($file, '.')) == $file_extension) {
          if (isset($HTTP_GET_VARS['list']) && ($HTTP_GET_VARS['list'] = 'new')) {
            if (!in_array($file, $modules_installed)) {
              $directory_array[] = $file;
            }
          } else {
            if (in_array($file, $modules_installed)) {
              $directory_array[] = $file;
            } else {
              $new_modules_counter++;
            }
          }
        }
      }
    }
    sort($directory_array);
    $dir->close();
  }
?>
    <div class="row">
      <div class="col-md-6">
        <h3><?php echo HEADING_TITLE; ?></h3>
      </div>
	
<?php
  if (isset($HTTP_GET_VARS['list'])) {
    echo '  <div class="col-md-6 text-right">' . tep_draw_button(IMAGE_BACK, 'fa fa-chevron-left', tep_href_link(FILENAME_MODULES, 'set=' . $set)) . '</div>';
  } else {
    echo '  <div class="col-md-6 text-right">' . tep_draw_button(IMAGE_MODULE_INSTALL . ' (' . $new_modules_counter . ')', 'fa fa-plus', tep_href_link(FILENAME_MODULES, 'set=' . $set . '&list=new'), 'primary', null, 'btn-default') . '</div>';
  }
?>
    </div>
<div class="row">    
    <div class="col-md-8">            
        <table class="table table-hover table-condensed table-responsive table-striped">
           <thead>     
     		  <tr>
                <th><?php echo TABLE_HEADING_MODULES; ?></th>
                <th><?php echo TABLE_HEADING_SORT_ORDER; ?></th>
                <th class="text-right"><?php echo TABLE_HEADING_ACTION; ?></th>
              </tr>
		   </thead>
           <tbody>		   
<?php
  $installed_modules = array();
  for ($i=0, $n=sizeof($directory_array); $i<$n; $i++) {
    $file = $directory_array[$i];

    include($module_language_directory . $language . '/modules/' . $module_type . '/' . $file);
    include($module_directory . $file);

    $class = substr($file, 0, strrpos($file, '.'));
    if (tep_class_exists($class)) {
      $module = new $class;
      if ($module->check() > 0) {
        if (($module->sort_order > 0) && !isset($installed_modules[$module->sort_order])) {
          $installed_modules[$module->sort_order] = $file;
        } else {
          $installed_modules[] = $file;
        }
      }

      if ((!isset($HTTP_GET_VARS['module']) || (isset($HTTP_GET_VARS['module']) && ($HTTP_GET_VARS['module'] == $class))) && !isset($mInfo)) {
        $module_info = array('code' => $module->code,
                             'title' => $module->title,
                             'description' => $module->description,
                             'status' => $module->check(),
                             'signature' => (isset($module->signature) ? $module->signature : null),
                             'api_version' => (isset($module->api_version) ? $module->api_version : null));

        $module_keys = $module->keys();

        $keys_extra = array();
        for ($j=0, $k=sizeof($module_keys); $j<$k; $j++) {
          $key_value_query = tep_db_query("select configuration_title, configuration_value, configuration_description, use_function, set_function from " . TABLE_CONFIGURATION . " where configuration_key = '" . $module_keys[$j] . "'");
          $key_value = tep_db_fetch_array($key_value_query);

          $keys_extra[$module_keys[$j]]['title'] = $key_value['configuration_title'];
          $keys_extra[$module_keys[$j]]['value'] = $key_value['configuration_value'];
          $keys_extra[$module_keys[$j]]['description'] = $key_value['configuration_description'];
          $keys_extra[$module_keys[$j]]['use_function'] = $key_value['use_function'];
          $keys_extra[$module_keys[$j]]['set_function'] = $key_value['set_function'];
        }

        $module_info['keys'] = $keys_extra;

        $mInfo = new objectInfo($module_info);
      }

      if (isset($mInfo) && is_object($mInfo) && ($class == $mInfo->code) ) {
        if ($module->check() > 0) {
          echo ' <tr class="info" onclick="document.location.href=\'' . tep_href_link(FILENAME_MODULES, 'set=' . $set . '&module=' . $class . '&action=edit') . '\'">';
        } else {
          echo ' <tr class="info">';
        }
      } else {
		  echo ' <tr onclick="document.location.href=\'' . tep_href_link(FILENAME_MODULES, 'set=' . $set . (isset($HTTP_GET_VARS['list']) ? '&list=new' : '') . '&module=' . $class) . '\'">';
      }
?>
                <td><?php echo $module->title; ?></td>
                <td><?php if (is_numeric($module->sort_order)) echo $module->sort_order; ?></td>
                <td class="text-right"><?php if (isset($mInfo) && is_object($mInfo) && ($class == $mInfo->code) ) { echo '<i class="fa fa-chevron-circle-right fa-lg mouse"></i>'; } else { echo '<a href="' . tep_href_link(FILENAME_MODULES, 'set=' . $set . (isset($HTTP_GET_VARS['list']) ? '&list=new' : '') . '&module=' . $class) . '"><i class="fa fa-info-circle fa-lg" title="'. IMAGE_ICON_INFO .'"></i></a>'; } ?>&nbsp;</td>
              </tr>
			
<?php
    }
  }

  if (!isset($HTTP_GET_VARS['list'])) {
    ksort($installed_modules);
    $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = '" . $module_key . "'");
    if (tep_db_num_rows($check_query)) {
      $check = tep_db_fetch_array($check_query);
      if ($check['configuration_value'] != implode(';', $installed_modules)) {
        tep_db_query("update " . TABLE_CONFIGURATION . " set configuration_value = '" . implode(';', $installed_modules) . "', last_modified = now() where configuration_key = '" . $module_key . "'");
      }
    } else {
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Installed Modules', '" . $module_key . "', '" . implode(';', $installed_modules) . "', 'This is automatically updated. No need to edit.', '6', '0', now())");
    }

    if ($template_integration == true) {
      $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'TEMPLATE_BLOCK_GROUPS'");
      if (tep_db_num_rows($check_query)) {
        $check = tep_db_fetch_array($check_query);
        $tbgroups_array = explode(';', $check['configuration_value']);
        if (!in_array($module_type, $tbgroups_array)) {
          $tbgroups_array[] = $module_type;
          sort($tbgroups_array);
          tep_db_query("update " . TABLE_CONFIGURATION . " set configuration_value = '" . implode(';', $tbgroups_array) . "', last_modified = now() where configuration_key = 'TEMPLATE_BLOCK_GROUPS'");
        }
      } else {
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Installed Template Block Groups', 'TEMPLATE_BLOCK_GROUPS', '" . $module_type . "', 'This is automatically updated. No need to edit.', '6', '0', now())");
      }
    }
  }
?>
          </tbody>
        </table>
	<div class="row">
		<div class="col-xs-12">
			<?php echo TEXT_MODULE_DIRECTORY . ' ' . $module_directory; ?>
		</div>
	</div>
	</div> <!-- EOF col-md-8 -->
	<div class="col-md-4">			  

<?php
  switch ($action) {
    case 'edit':
      $keys = '';
      reset($mInfo->keys);
      while (list($key, $value) = each($mInfo->keys)) {
        $keys .= '<strong>' . $value['title'] . '</strong><br />' . $value['description'] . '<br />';

        if ($value['set_function']) {
          eval('$keys .= ' . $value['set_function'] . "'" . $value['value'] . "', '" . $key . "');");
        } else {
          $keys .= tep_draw_input_field('configuration[' . $key . ']', $value['value']);
        }
        $keys .= '<br /><br />';
      }
      $keys = substr($keys, 0, strrpos($keys, '<br /><br />'));
		
		echo '<div class="panel panel-primary">
			<div class="panel-heading"><span class="panel-title">' . $mInfo->title . '</span></div>';
			
			echo '<div class="panel-body">' .
					tep_draw_form('modules', FILENAME_MODULES, 'set=' . $set . '&module=' . $HTTP_GET_VARS['module'] . '&action=save') . $keys .
					'<br /><div class="text-center">' . tep_draw_button(IMAGE_SAVE, 'fa fa-floppy-o', null, 'primary', null, 'btn-success') . 
											 '&nbsp;' . tep_draw_button(IMAGE_CANCEL, 'fa fa-ban icon-red', tep_href_link(FILENAME_MODULES, 'set=' . $set . '&module=' . $HTTP_GET_VARS['module'])) . '</div>' .
					'</div></div>';
      break;
    default:
		echo '<div class="panel panel-default">
			<div class="panel-heading"><span class="panel-title"><strong>' . $mInfo->title . '</strong></span></div>';
			
			echo '<div class="panel-body">';
			
      if ($mInfo->status == '1') {
        $keys = '';
        reset($mInfo->keys);
        while (list(, $value) = each($mInfo->keys)) {
          $keys .= '<div class="text-fix-lg"><strong>' . $value['title'] . '</strong></div><br />';
          if ($value['use_function']) {
            $use_function = $value['use_function'];
            if (preg_match('/->/', $use_function)) {
              $class_method = explode('->', $use_function);
              if (!isset(${$class_method[0]}) || !is_object(${$class_method[0]})) {
                include(DIR_WS_CLASSES . $class_method[0] . '.php');
                ${$class_method[0]} = new $class_method[0]();
              }
              $keys .= tep_call_function($class_method[1], $value['value'], ${$class_method[0]});
            } else {
              $keys .= tep_call_function($use_function, $value['value']);
            }
          } else {
            $keys .= '<div class="text-fix-lg">' . $value['value'] . '</div>';
          }
          $keys .= '<br /><br />';
        }
        $keys = substr($keys, 0, strrpos($keys, '<br /><br />'));

			echo '<br /><div class="text-center">' . tep_draw_button(IMAGE_EDIT, 'fa fa-pencil', tep_href_link(FILENAME_MODULES, 'set=' . $set . '&module=' . $mInfo->code . '&action=edit'), 'primary', null, 'btn-warning') . '&nbsp;' . 
				 tep_draw_button(IMAGE_MODULE_REMOVE, 'fa fa-minus', tep_href_link(FILENAME_MODULES, 'set=' . $set . '&module=' . $mInfo->code . '&action=remove'), 'primary', null, 'btn-danger') . '</div>';

        if (isset($mInfo->signature) && (list($scode, $smodule, $sversion, $soscversion) = explode('|', $mInfo->signature))) {
			echo '<br /><i class="fa fa-info-circle fa-lg" title="'. IMAGE_ICON_INFO .'"></i>&nbsp;<strong>' . TEXT_INFO_VERSION . '</strong> ' . $sversion . ' (<a href="http://sig.oscommerce.com/' . $mInfo->signature . '" target="_blank">' . TEXT_INFO_ONLINE_STATUS . '</a>)';
        }

        if (isset($mInfo->api_version)) {
			echo '<i class="fa fa-info-circle fa-lg" title="'. IMAGE_ICON_INFO .'"></i>&nbsp;<strong>' . TEXT_INFO_API_VERSION . '</strong> ' . $mInfo->api_version;
        }

			echo '<br />' . $mInfo->description;
			echo '<br /><br />' . $keys;
      } elseif (isset($HTTP_GET_VARS['list']) && ($HTTP_GET_VARS['list'] == 'new')) {
        if (isset($mInfo)) {
			echo '<br /><div class="text-center">' . tep_draw_button(IMAGE_MODULE_INSTALL, 'fa fa-plus', tep_href_link(FILENAME_MODULES, 'set=' . $set . '&module=' . $mInfo->code . '&action=install')) . '</div>';

          if (isset($mInfo->signature) && (list($scode, $smodule, $sversion, $soscversion) = explode('|', $mInfo->signature))) {
			echo '<br /><i class="fa fa-info-circle fa-lg" title="'. IMAGE_ICON_INFO .'"></i>&nbsp;<strong>' . TEXT_INFO_VERSION . '</strong> ' . $sversion . ' (<a href="http://sig.oscommerce.com/' . $mInfo->signature . '" target="_blank">' . TEXT_INFO_ONLINE_STATUS . '</a>)';
          }

          if (isset($mInfo->api_version)) {
            echo '<br /><i class="fa fa-info-circle fa-lg" title="'. IMAGE_ICON_INFO .'"></i>&nbsp;<strong>' . TEXT_INFO_API_VERSION . '</strong> ' . $mInfo->api_version;
          }

			echo '<br />' . $mInfo->description;
        }
      }
	  		echo '</div></div>';
      break;
  }
?>

    </div> <!-- EOF col-md-4 //--> 
</div> <!-- EOF row //--> 

<?php
  require(DIR_WS_INCLUDES . 'template_bottom.php');
  require(DIR_WS_INCLUDES . 'application_bottom.php');
?>