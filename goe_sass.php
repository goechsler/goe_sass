<?php

if (php_sapi_name() == 'cli') {
  $plugin['version'] = '0.1';
  $plugin['author'] = 'Georg Oechsler';
  $plugin['author_uri'] = 'http://txp.oechsler.de/';
  $plugin['description'] = 'Allow use of SASS for CSS output.';

  // Advanced guessing :/ These constants are from textpattern/lib/constants.php
  define('PLUGIN_LIFECYCLE_NOTIFY', 0x0002);
  $plugin['flags'] = PLUGIN_LIFECYCLE_NOTIFY;

  // 1 = admin plugin; loaded on both the public and admin side
  $plugin['type'] = 1;

  echo goe_sass_compile_plugin();
  exit;
}

// -----------------------------------------------------

function goe_sass_extract_section($lines, $section) {
  $result = "";

  $start_delim = "# --- BEGIN PLUGIN $section ---";
  $end_delim = "# --- END PLUGIN $section ---";

  $start = array_search($start_delim, $lines) + 1;
  $end = array_search($end_delim, $lines);

  $content = array_slice($lines, $start, $end-$start);

  return join("\n", $content);

}

function goe_sass_compile_plugin($file='') {
  global $plugin;

  if (empty($file))
    $file = $_SERVER['SCRIPT_FILENAME'];

  if (!isset($plugin['name'])) {
    $plugin['name'] = basename($file, '.php');
  }

  # Read the contents of this file, and strip line ends
  $content = file($file);
  for ($i=0; $i < count($content); $i++) {
    $content[$i] = rtrim($content[$i]);
  }

  $plugin['help'] = goe_sass_extract_section($content, 'HELP');
  $plugin['code'] = goe_sass_extract_section($content, 'CODE');

  @include('classTextile.php');
  if (class_exists('Textile')) {
    $textile = new Textile();
    $plugin['help'] = $textile->TextileThis($plugin['help']);
  }

  $plugin['md5'] = md5( $plugin['code'] );

  // to produce a copy of the plugin for distribution, load this file in a browser.

  header('Content-type: text/plain');
  $header = <<<EOF
# {$plugin['name']} v{$plugin['version']}
# {$plugin['description']}
# {$plugin['author']}
# {$plugin['author_uri']}

# ......................................................................
# This is a plugin for Textpattern - http://textpattern.com/
# To install: textpattern > admin > plugins
# Paste the following text into the 'Install plugin' box:
# ......................................................................
EOF;

  return $header . "\n\n" . trim(chunk_split(base64_encode(serialize($plugin)), 72)). "\n";

}

if (0) {
?>
# --- BEGIN PLUGIN HELP ---

<h1>Sassify Textpattern!</h1>

<p>This plugin allows the use of <a href="http://sass-lang.com/">SASS</a> for CSS preprocessing
within textpattern.</p>

<p>It will compile the SASS code you define within the regular CSS style form to
static CSS files, serving it through a tag which is quite similar to the well
known &lt;txp:css /&gt; tag.</p>

<p>This plugin relies on <a href="http://phpsass.com/">phpSASS</a> and borrows code from the
<a href="https://drupal.org/project/sassy">sassy</a> module for Drupal.</p>

<p>It started off as a hack of <a href="http://textpattern.org/plugins/934/rvm_css">rvm_css</a>,
which can do similar things with LESS. Under the hood you will find that it is still
heavily based on the code of the great <a href="http://vanmelick.com/">Ruud van Melick</a>.</p>

<h2>Installation</h2>

<ol>
<li>Grab a copy of <a href="http://phpsass.com/">PHPSass</a> and put it somewhere your
webserver can find it. The default location is &#8216;textpattern/vendor/phpsass&#8217;.</li>
<li>Create a directory for the static CSS files on your webserver. You should make
sure that PHP is able to write to that directory. The default path to this
directory is /css.</li>
<li>Install and enable the plugin like any other plugin out there.</li>
<li>Check and adjust the configuration under the extensions tab. It ships with
sane default. See section &#8220;Configuration&#8221; for details.</li>
<li>If you want to play safe: Resave a CSS style definition form and see if there
is a file written to the output directory you have configured in step 2. You&#8217;ll
need to resave all your style sheets anyway to create the static files.</li>
<li>Once your set you may replace all occurrences of &lt;txp:css /&gt; with
&lt;txp:goe_sass /&gt;.</li>
</ol>

<p>The &lt;txp:goe_sass /&gt; tag supplied by this plugin has the exact same
attributes as the built-in &lt;txp:css /&gt; tag and can be used as a drop-in
replacement.</p>

<p>Note: Because not all characters are allowed in filenames, avoid using
non-alphanumeric characters in style sheet names.</p>

<h2>Configuration</h2>

<p>There are four configuration items for this plugin:</p>

<dl>
<dt>Path to PHPSass</dt>
<dd>The filesystem path to PHPSass. This is where the file SassParser.php sits in.</dd>
<dd>default value: [path to textpattern directory] + vendor/phpsass</dd>
</dl>

<dl>
<dt>CSS Output Directory</dt>
<dd>The location of the css output directory relative to document root.</dd>
<dd>default value: /css</dd>
</dl>

<dl>
<dt>Output Style</dt>
<dd>There are several output styles to choose from. Choice is yours.</dd>
<dd>nested: Each property+selector takes up 1 line selector indentation reflects nesting depth.</dd>
<dd>expanded: Each property+selector takes up 1 line no selector indentation.</dd>
<dd>compact: Each selector takes up 1 line with properties on the same line. No indentation.</dd>
<dd>compressed: Compressed: Almost no whitespace designed to be as space-efficient as possible.</dd>
<dd>default value: nested</dd>
</dl>

<dl>
<dt>Language Syntax</dt>
<dd>PHPSass supports two syntax options.</dd>
<dd>scss: The regular &#8220;Sassy CSS&#8221; style which is a superset of CSS3&#8217;s syntax.</dd>
<dd>sass: The older syntax known as the indented syntax.</dd>
<dd>default value: scss</dd>
</dl>
</div>


# --- END PLUGIN HELP ---
<?php
}

# --- BEGIN PLUGIN CODE ---
if (txpinterface == 'admin') {
  register_callback('goe_sass_css_save', 'css', 'css_save');
  register_callback('goe_sass_css_save', 'css', 'css_save_posted');
  register_callback('goe_sass_css_save', 'css', 'del_dec');
  register_callback('goe_sass_css_delete', 'css', 'css_delete');
  register_callback('goe_sass_cleanup', 'plugin_lifecycle.goe_sass', 'deleted');
  register_callback('goe_sass_default_prefs', 'plugin_lifecycle.goe_sass', 'enabled');
  register_callback('goe_sass_prefs', 'plugin_prefs.goe_sass');

  // Register event sassconfig with own tab under extensions and respective callback.
  add_privs('goe_sass_config');
  register_tab('extensions', 'goe_sass_config', gTxt('SASS Configuration'));
  register_callback('goe_sass_config', 'goe_sass_config');
}


/**
 * Code for the <txp:goe_sass /> tag.
 *
 * Behaves pretty much like <txp:css /> tag, but links our compiled SASS output
 * as static files.
 */
function goe_sass($atts) {
  global $txp_error_code, $s, $path_to_site, $version;

  $goe_sass_css_dir = get_pref('goe_sass_css_dir');

  extract(lAtts(array(
    'format' => 'url',
    'media'  => 'screen',
    'n'      => '',
    'name'   => '',
    'rel'    => 'stylesheet',
    'title'  => '',
  ), $atts));

  if (!$n and !$name) {
    if ($s) {
      $name = safe_field('css', 'txp_section', "name='".doSlash($s)."'");
    }
    else {
      $name = 'default';
    }
  }
  elseif (!$name) {
    $name = $n;
  }

  // Dispose leading slash on $goe_sass_css_dir, if any, to prevent double
  // slashes in output.
  $goe_sass_css_dir = preg_replace('#^/#', '', $goe_sass_css_dir);
  $file = $goe_sass_css_dir . '/' . strtolower(sanitizeForUrl($name)).'.css';

  if (empty($goe_sass_css_dir) or !is_readable($path_to_site.'/'.$file)) {
    if (version_compare($version, '4.3.0', '>=')) {
      unset($atts['n']);
      $atts['name'] = $name;
    } else {
      unset($atts['name']);
      $atts['n'] = $name;
    }
    return css($atts);
  }

  if ($format == 'link') {
    return '<link rel="' . txpspecialchars($rel) . '" type="text/css"'.
      ($media ? ' media="' . txpspecialchars($media) . '"' : '').
      ($title ? ' title="' . txpspecialchars($title) . '"' : '').
      ' href="' . hu . txpspecialchars($file) . '" />';
  }

  return hu . txpspecialchars($file);
}

/**
 * Callback for the "css_save" event.
 *
 * Compile SASS code from database and save it to CSS files.
 */
function goe_sass_css_save() {
  global $path_to_site;

  $vars = goe_sass_vars();
  foreach ($vars as $var => $data) {
    $$var = get_pref('$var');
  }

  $name = (ps('copy') or ps('savenew')) ? ps('newname') : ps('name');
  $filename = strtolower(sanitizeForUrl($name));

  if (empty($goe_sass_css_dir) or !$filename) {
    return;
  }

  $css = safe_field('css', 'txp_css', "name='".doSlash($name)."'");

  if ($css) {
    if (preg_match('!^[a-zA-Z0-9/+]*={0,2}$!', $css)) {
      $css = base64_decode($css);
    }

    $library = $goe_sass_path . '/SassParser.php';
    $template_dir = $path_to_site . $goe_sass_css_dir . '/sass-templates';

    if (file_exists($library)) {
      try {
        require_once ($library);

        $options = array(
          'debug' => TRUE,
          'syntax' => $goe_sass_style,
          'style' => $goe_sass_style,
          'load_paths' => array($template_dir),
        );

        $parser = new SassParser($options);
        $css = $parser->toCss($css, false);
      }
      catch (Exception $e) {
        print "<script>alert('SASS Error: " . preg_replace('/\n/', ' ', $e->getMessage()) . "');</script>";
        return;
      }
    }

    $file = $path_to_site . '/' . $goe_sass_css_dir . '/' . $filename;
    $handle = fopen($file . '.css', 'wb');
    fwrite($handle, $css);
    fclose($handle);
    chmod($file . '.css', 0644);
  }
}


/**
 * Callback for the "css" event.
 * Triggered when css is deleted.
 *
 * Unlink compiled css files.
 */
function goe_sass_css_delete()
{
  global $path_to_site;

  $goe_sass_css_dir = get_pref('goe_sass_css_dir');

  if (safe_field('css', 'txp_css', "name='".doSlash(ps('name'))."'")) {
    return;
  }

  $name = strtolower(sanitizeForUrl(ps('name')));
  $file = $path_to_site . $goe_sass_css_dir . '/' . $name;

  if (!empty($goe_sass_css_dir) and $name) {
    unlink($file.'.css');
  }
}

/**
 * Helper returning configuration variable data.
 */
function goe_sass_vars() {
  global $path_to_site;

  return array(
    'goe_sass_path' => array(
      'title' => 'Path to PHPSass',
      'default' => txpath . '/vendor/phpsass',
    ),
    'goe_sass_css_dir' => array(
      'title' => 'CSS Output Directory',
      'default' => '/css',
    ),
    'goe_sass_style' => array(
      'title' => 'Output Style',
      'default' => 'nested',
    ),
    'goe_sass_syntax' => array(
      'title' => 'Language Syntax',
      'default' => 'scss',
    ),
  );
}

/**
 * Callback for the "plugin_lifecycle" event.
 * Triggered when the plugin is deleted.
 *
 * Dispose config variables.
 */
function goe_sass_cleanup($event, $step) {
  $vars = goe_sass_vars();

  //Delete config variables.
  foreach (array_keys($vars) as $var) {
    safe_delete('txp_prefs', "name='$var'");
  }
}

/**
 * Callback for the "goe_sass_config" event.
 *
 * Show and process configuration form.
 */
function goe_sass_config($event, $step) {

  include(txpath . '/include/txp_prefs.php');

  $vars = goe_sass_vars();

  // Read value from database or use defaults
  foreach ($vars as $var => $data) {
    $$var = get_pref($var);

    if (! $$var) {
      if ($$var === '') {
        safe_update('txp_prefs', "val = '{$data['default']}'", "name = '$var' and prefs_id ='1'");
      }
      else {
        safe_insert('txp_prefs', "name='$var', val='{$data['default']}', prefs_id ='1'");
      }
      $$var = $data['default'];
    }
  }

  // Save values if form was submitted
  if (gps("submit")) {
    foreach (array_keys($vars) as $var) {
      safe_update('txp_prefs', "val = '".addslashes(ps($var))."'","name = '$var' and prefs_id ='1'");
    }
    header("Location: index.php?event=goe_sass_config");
  }

  // Output form
  $sassConfigTxt = gTxt("SASS Configuration");
  pagetop($sassConfigTxt);

  $tdaStyle = ' style="text-align:right;vertical-align:middle"';
  $form = startTable("list") . tr(tdcs(hed($sassConfigTxt,1),2));

  foreach ($vars as $var => $data) {
    $form .= tr(tda(gTxt($data['title']), $tdaStyle) . tda(text_input($var, $$var, '40'), ''));
  }

  $form .= tr(tda(graf(fInput("submit","submit", gTxt("Submit"), "publish")), ' colspan="2"'));
  $form .= eInput("goe_sass_config");
  $form .= endTable();

  echo form($form);
}

/**
 * Callback for the "plugin_lifecycle" event.
 * Triggered when the plugin is enabled.
 *
 * Setup default config variables.
 */
function goe_sass_default_prefs($event, $step) {

  include(txpath . '/include/txp_prefs.php');

  $vars = goe_sass_vars();

  // If variables are not set or empty write default values.
  foreach ($vars as $var => $data) {
    $$var = get_pref($var);

    if (! $$var) {
      if ($$var === '') {
        safe_update('txp_prefs', "val = '{$data['default']}'", "name = '$var' and prefs_id ='1'");
      }
      else {
        safe_insert('txp_prefs', "name='$var', val='{$data['default']}', prefs_id ='1'");
      }
    }
  }
}

# --- END PLUGIN CODE ---
