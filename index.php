<?php
/**
 * COPS (Calibre OPDS PHP Server) HTML main script
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Sébastien Lucas <sebastien@slucas.fr>
 *
 */

use SebLucas\Cops\Calibre\Base;
use SebLucas\Cops\Input\Config;
use SebLucas\Cops\Input\Request;
use SebLucas\Cops\Output\Format;
use SebLucas\Cops\Output\JSONRenderer;
use SebLucas\Cops\Pages\Page;
use SebLucas\Template\doT;

require_once dirname(__FILE__) . '/config.php';
/** @var array $config */

// If we detect that an OPDS reader try to connect try to redirect to feed.php
if (preg_match('/(MantanoReader|FBReader|Stanza|Marvin|Aldiko|Moon\+ Reader|Chunky|AlReader|EBookDroid|BookReader|CoolReader|PageTurner|books\.ebook\.pdf\.reader|com\.hiwapps\.ebookreader|OpenBook)/', $_SERVER['HTTP_USER_AGENT'])) {
    header('location: ' . Config::ENDPOINT["feed"]);
    exit();
}

$request = new Request();
$page     = $request->get('page');
$query    = $request->get('query');
$qid      = $request->get('id');
$n        = $request->get('n', '1');
$database = $request->get('db');

// Use the configured home page if needed
if (!isset($page)) {
    $page = Page::INDEX;
    if (!empty($config['cops_home_page']) && defined('SebLucas\Cops\Pages\Page::' . $config['cops_home_page'])) {
        $page = constant('SebLucas\Cops\Pages\Page::' . $config['cops_home_page']);
    }
    $request->set('page', $page);
}

// Access the database ASAP to be sure it's readable, redirect if that's not the case.
// It has to be done before any header is sent.
Base::checkDatabaseAvailability($database);

if ($config ['cops_fetch_protect'] == '1') {
    session_start();
    if (!isset($_SESSION['connected'])) {
        $_SESSION['connected'] = 0;
    }
}

header('Content-Type:text/html;charset=utf-8');

$data = ['title'                 => $config['cops_title_default'],
              'version'               => Config::VERSION,
              'opds_url'              => $config['cops_full_url'] . Config::ENDPOINT["feed"],
              'customHeader'          => '',
              'template'              => $request->template(),
              'server_side_rendering' => $request->render(),
              'current_css'           => $request->style(),
              'favico'                => $config['cops_icon'],
              'getjson_url'           => JSONRenderer::getCurrentUrl($request->query())];
if (preg_match('/Kindle/', $request->agent())) {
    $data['customHeader'] = '<style media="screen" type="text/css"> html { font-size: 75%; -webkit-text-size-adjust: 75%; -ms-text-size-adjust: 75%; }</style>';
}
$headcontent = file_get_contents('templates/' . $request->template() . '/file.html');
$template = new doT();
$dot = $template->template($headcontent, null);
echo($dot($data));
?><body>
<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
if ($request->render()) {
    // Get the data
    $data = JSONRenderer::getJson($request, true);

    echo Format::serverSideRender($data, $request->template());
}
?>
</body>
</html>
