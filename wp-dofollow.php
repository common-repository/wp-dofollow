<?php
/*
Plugin Name: Wp-Dofollow
Plugin URI: http://wordpresssmarts.com/category/wordpress-plugins/
Description: Selectively disable the "nofollow" tagging for comments. 
Author: Suravi Nandi
Version: 0.5
Author URI: http://wordpresssmarts.com/
*/

if (is_plugin_page()):
    if (isset($wppDoFollow)) {
	$wppDoFollow->options_page();
    } else {
?>
<div class="wrap">
<h2><?php echo __('Wp-Dofollow Options'); ?></h2>
<p><?php echo __('You need to activate the plugin first, before viewing the options page.'); ?></p>
</div>
<?php
    }
else:

define(DOFOLLOW_NORMAL, 0);
define(DOFOLLOW_REGONLY, 1);
define(DOFOLLOW_REGOK, 2);
define(DOFOLLOW_OFF, 3);

class DoFollow
{
    var $ldate = 0;
    var $registered = false;
    var $timeout = 0;
    var $excludetypes = array();

    function DoFollow()
    {
	$this->init_options();

	add_filter('comment_text',
	    array(&$this, 'nofollow_del'), 16);

	add_filter('get_comment_author_link',
	    array(&$this, 'nofollow_del'), 16);

	remove_filter('pre_comment_content', 'wp_rel_nofollow', 15);
	if ($this->timeout)
	    add_filter('pre_comment_content',
		array(&$this, 'nofollow_ins'), 15);

	add_action('admin_menu', array(&$this, 'admin_menu'));
	// Broken in WordPress 3.0
	//add_action('options_page_dofollow', array(&$this, 'options_page'));
    }

    function admin_menu()
    {
	add_options_page(
	    __('Wp-Dofollow Options'),
	    __('Wp-Dofollow'), 5, basename(__FILE__));
    }

    function init_options()
    {
	if ($this->timeout = intval(get_option('dofollow_timeout'))) {
	    $this->ldate = time() - ($this->timeout * 86400);
	} else {
	    // Make it tomorrow, so we don't have to worry about time zones
	    $this->ldate = time() + 86400;
	}

	$this->registered = get_option('dofollow_registered');
	switch ($this->registered) {
	    case DOFOLLOW_NORMAL:
	    case DOFOLLOW_REGONLY:
	    case DOFOLLOW_REGOK:
	    case DOFOLLOW_OFF:
		break;
	    default:
		$this->registered = DOFOLLOW_NORMAL;
		break;
	}

	$this->excludetypes = get_option('dofollow_excludetypes');
	if (is_array($this->excludetypes)) {
	    foreach ($this->excludetypes as $type => $val) {
		switch ($val) {
		    case 0:
		    case 1:
			break;
		    default:
			$this->excludetypes[$type] = 0;
			break;
		}
	    }
	} else {
	    $this->excludetypes = array();
	}
    }

    function options_page()
    {
	global $wpdb;

	if (isset($_POST['Submit'])):

	    update_option('dofollow_registered',
		intval($_POST['dofollow_registered']));

	    update_option('dofollow_timeout',
		intval($_POST['dofollow_timeout']));

	    switch (isset($_POST['dofollow_excludetypes'])) {
		case true:
		    update_option('dofollow_excludetypes',
			$_POST['dofollow_excludetypes']);
		    break;
		default:
		    delete_option('dofollow_excludetypes');
		    break;
	    }

	    // use the new values
	    $this->init_options();
?>
<div class="updated">
<strong><?php _e('Options saved.') ?></strong>
</div>
<?php
	endif;

	if ($this->timeout = intval(get_option('dofollow_timeout'))) {
	    $this->ldate = time() - ($this->timeout * 86400);
	}

	$alldb = $wpdb->get_col("SELECT DISTINCT comment_type FROM {$wpdb->comments}");
	foreach ($alldb as $type) {
	  switch ($type) {
	    case '':
	      // regular comments
	      break;
	    default:
	      $alltypes[$type] = true;
	      break;
	  }
	}
	$alltypes['pingback'] = true;
	$alltypes['trackback'] = true;
	ksort($alltypes);

	if (empty($this->excludetypes)) {
	    $elist = '';
	} else {
	    if (count($this->excludetypes) > 1) {
		$elist = array_keys($this->excludetypes);
		$last = array_pop($elist);
		$elist = implode(', ', $elist) . __(' or ') . $last;
	    } else {
		$elist = implode('', array_keys($this->excludetypes));
	    }
	}

	if ($this->timeout) {
	    $opmode = sprintf(__('Timeout is set to %s days.'), $this->timeout);
	    if ($this->registered == DOFOLLOW_REGONLY) {
		$opmode .= ' ' . sprintf(__('Nofollow will be removed from <strong>comments posted by registered users</strong> before %s.'), date('r (T)', $this->ldate));
		$opmode .= ' ' . __('Nofollow will not be removed from pingbacks, trackbacks or other special comment types.');
	    } elseif ($this->registered == DOFOLLOW_OFF) {
		if (empty($elist)) {
		    $opmode .= ' ' . sprintf(__('Nofollow will be removed from <strong>pingbacks, trackbacks and other special comment types</strong> posted before %s.'), date('r (T)', $this->ldate));
		} else {
		    $opmode .= ' ' . sprintf(__('Nofollow will be removed from comments posted before %s <strong>except</strong> from regular comments or comments of type %s.'), date('r (T)', $this->ldate), $elist);
		}
	    } else {
		if (empty($elist)) {
		    $opmode .= ' ' . sprintf(__('Nofollow will be removed from <strong>all comments</strong> posted before %s.'), date('r (T)', $this->ldate));
		} else {
		    $opmode .= ' ' . sprintf(__('Nofollow will be removed from comments posted before %s <strong>except</strong> from comments of type %s.'), date('r (T)', $this->ldate), $elist);
		}
		if ($this->registered == DOFOLLOW_REGOK) {
		    $opmode .= ' ' . __('However, nofollow will be removed immediately from comments posted by registered users.');
		}
	    }
	} else {
	    $opmode = __('No timeout is set.');
	    if ($this->registered == DOFOLLOW_REGONLY) {
		$opmode .= ' ' . __('Nofollow will be removed immediately from <strong>comments posted by registered users</strong>.');
		$opmode .= ' ' . __('Nofollow will not be removed from pingbacks, trackbacks or other special comment types.');
	    } elseif ($this->registered == DOFOLLOW_OFF) {
		if (empty($elist)) {
		    $opmode .= ' ' . __('Nofollow will be removed immediately from <strong>pingbacks, trackbacks and other special comment types</strong>.');
		} else {
		    $opmode .= ' ' . sprintf(__('Nofollow will be removed immediately <strong>except</strong> from regular comments or comments of type %s.'), $elist);
		}
	    } else {
		if (empty($elist)) {
		    $opmode .= ' ' . __('Nofollow will be removed immediately from <strong>all comments</strong>.');
		} else {
		    $opmode .= ' ' . sprintf(__('Nofollow will be removed immediately <strong>except</strong> from comments of type %s.'), $elist);
		}
	    }
	}
?>
<div class="wrap">
<h2><?php echo __('Wp-Dofollow Options'); ?></h2>
<div align="right">
      <table width="265" border="0">
        <tr>
          <td width="259" height="50"><ul>
            <li><a href="http://wordpresssmarts.com/category/wordpress-plugins" title="WP FollowMe plugin page" target="_blank">Plugin Homepage</a>
            </li>
            <li><a href="http://wordpresssmarts.com" title="Visit wpBurn.com Website" target="_blank">WordpressSmarts.com Website</a><a href="http://wordpresssmarts.com/category/wordpress-plugins/" title="WP FollowMe plugin page" target="_blank"> </a></li>
            <li><a href="http://wordpresssmarts.com/category/wordpress-themes" title="wpBurn Blue Wordpress Theme" target="_blank">Wordpress themes</a></li>
            <li><a href="http://wordpress.org/extend/plugins/wp-dofollow/" title="Support for WP FollowMe plugin" target="_blank">Support for this plugin</a></li>
          </ul>            </td>
          </tr>
      </table>
         </div>
<form name="dofollow" method="post" action="">
  <input type="hidden" name="action" value="update" />
  <input type="hidden" name="page_options" value="'dofollow_registered dofollow_timeout'" />
  <fieldset class="options">
    <legend><?php echo _e('Wp-Dofollow operating mode'); ?></legend>
        <table cellspacing="2" cellpadding="5" class="editform">
      <tr valign="baseline">
	<td><?php echo $opmode; ?></td>
      </tr>
    </table>
  </fieldset>
  <fieldset class="options">
    <legend><?php echo __('Timeout'); ?></legend>
    <table cellspacing="2" cellpadding="5" class="editform">
      <tr valign="baseline">
	<td><?php echo sprintf(
	  __('Remove nofollow from comments older than %s days.'),
	  '<input name="dofollow_timeout" type="text"'
	  . ' value="' . $this->timeout . '" size="3" align="right" />');
	?>
	</td>
      </tr>
    </table>
  </fieldset>
  <fieldset class="options">
    <legend><?php echo __('Comments'); ?></legend>
    <table cellspacing="2" cellpadding="5" class="editform">
      <tr valign="baseline">
	<td>
	  <input name="dofollow_registered" type="radio" value="<?php
	    echo DOFOLLOW_NORMAL; ?>"<?php
	    if ($this->registered == DOFOLLOW_NORMAL) echo ' checked';
	    ?> />
	  <?php _e('Remove nofollow from comments posted by registered users and other visitors.'); ?><br />
	  <input name="dofollow_registered" type="radio" value="<?php
	    echo DOFOLLOW_REGONLY; ?>"<?php
	    if ($this->registered == DOFOLLOW_REGONLY) echo ' checked';
	    ?> />
	  <?php _e('Only remove nofollow from comments posted by registered users.'); ?><br />
	  <input name="dofollow_registered" type="radio" value="<?php
	    echo DOFOLLOW_REGOK; ?>"<?php
	    if ($this->registered == DOFOLLOW_REGOK) echo ' checked';
	    ?> />
	  <?php _e('Remove nofollow immediately from comments posted by registered users and use the timeout for other visitors.'); ?><br />
	  <input name="dofollow_registered" type="radio" value="<?php
	    echo DOFOLLOW_OFF; ?>"<?php
	    if ($this->registered == DOFOLLOW_OFF) echo ' checked';
	    ?> />
	  <?php _e('Do not remove nofollow from regular comments.'); ?><br />
	</td>
      </tr>
    </table>
  </fieldset>
  <fieldset class="options">
    <legend><?php echo __('Pingbacks, trackbacks and other special comment types'); ?></legend>
    <table cellspacing="2" cellpadding="5" class="editform">
      <tr valign="baseline">
	<td>
	  <input name="dofollow_excludetypes[pingback]" type="checkbox"
	    value="1"<?php
	    if (isset($this->excludetypes['pingback'])) echo ' checked';
	    ?>" />
	  <?php _e('Do not remove nofollow from pingbacks.'); ?><br />
	  <input name="dofollow_excludetypes[trackback]" type="checkbox"
	    value="1"<?php
	    if (isset($this->excludetypes['trackback'])) echo ' checked';
	    ?>" />
	  <?php _e('Do not remove nofollow from trackbacks.'); ?><br />
<?php
    foreach ($alltypes as $type => $v) {
	switch ($type) {
	    case 'pingback':
	    case 'trackback':
		break;
	    default:
?>
	  <input name="dofollow_excludetypes[<?php
	    echo htmlspecialchars($type); ?>]" type="checkbox"
	    value="1"<?php
	    if (isset($this->excludetypes[$type])) echo ' checked';
	    ?>" />
	  <?php
	    printf(
	      __('Do not remove nofollow from comments of custom type "%s".'),
	      htmlspecialchars($type)
	    ); ?><br />
<?php
		break;
	}
    }
?>
	</td>
      </tr>
    </table>
  </fieldset>
  <p class="submit">
    <input type="submit" name="Submit"
      value="<?php _e('Update Options') ?> &raquo;" />
  </p>
</form>
</div>
<?php
    }

    function nofollow_del($text)
    {
	global $comment;

	if (mysql2date('U', $comment->comment_date) > $this->ldate) {
	    if ($this->registered == DOFOLLOW_REGOK) {
		if (empty($comment->user_id)) {
		    return $text;
		}
	    } else {
		return $text;
	    }
	}

	if ($this->registered == DOFOLLOW_REGONLY) {
	    if (empty($comment->user_id)) {
		return $text;
	    }
	}

	if ($this->registered == DOFOLLOW_OFF) {
	    if (empty($comment->comment_type)) {
		return $text;
	    }
	}

	if (isset($this->excludetypes[$comment->comment_type])) {
	    return $text;
	}

	$text = preg_replace('|<a (.*)rel=([\'"])nofollow\2( (.+))?>|i',
	    '<a $1$3>', $text);
	$text = preg_replace('|<a (.*)rel=([\'"])nofollow (.+)\2( (.+))?>|i',
	    '<a $1rel=$2$3$2$4>', $text);
	$text = preg_replace('|<a (.*)rel=([\'"])(.+) nofollow\2( (.+))?>|i',
	    '<a $1rel=$2$3$2$4>', $text);
	$text = preg_replace('|<a (.*)rel=([\'"])(.+) nofollow (.+)\2( (.+))>|i',
	    '<a $1rel=$2$3 $4$2$5>', $text);

	return $text;
    }

    function nofollow_ins($text)
    {
	global $comment;

	if (mysql2date('U', $comment->comment_date) > $this->ldate) {
	    if ($this->registered == DOFOLLOW_REGOK) {
		if (empty($comment->user_id)) {
		    return wp_rel_nofollow($text);
		}
	    } else {
		return wp_rel_nofollow($text);
	    }
	}

	if ($this->registered == DOFOLLOW_REGONLY) {
	    if (empty($comment->user_id)) {
		return wp_rel_nofollow($text);
	    }
	}

	if ($this->registered == DOFOLLOW_OFF) {
	    if (empty($comment->comment_type)) {
		return wp_rel_nofollow($text);
	    }
	}

	if (isset($this->excludetypes[$comment->comment_type])) {
	    return wp_rel_nofollow($text);
	}

	return $text;
    }
}

$wppDoFollow =& new DoFollow;

endif; // is_plugin_page()

?>
