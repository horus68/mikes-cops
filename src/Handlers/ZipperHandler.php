<?php
/**
 * COPS (Calibre OPDS PHP Server) class file
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Sébastien Lucas <sebastien@slucas.fr>
 * @author     mikespub
 */

namespace SebLucas\Cops\Handlers;

use SebLucas\Cops\Input\Config;
use SebLucas\Cops\Output\FileResponse;
use SebLucas\Cops\Output\Response;
use SebLucas\Cops\Output\Zipper;

/**
 * Download all books for a page, series or author by format (epub, mobi, any, ...)
 * URL format: index.php/zipper?page={page}&type={type}&id={id}
 */
class ZipperHandler extends BaseHandler
{
    public const HANDLER = "zipper";

    public static function getRoutes()
    {
        // handle endpoint with page param
        return [
            "/zipper/{page}/{type}/{id}" => [static::PARAM => static::HANDLER],
            "/zipper/{page}/{type}" => [static::PARAM => static::HANDLER],
            "/zipper/{page}" => [static::PARAM => static::HANDLER],
        ];
    }

    public function handle($request)
    {
        if (empty(Config::get('download_page')) &&
            empty(Config::get('download_series')) &&
            empty(Config::get('download_author'))
        ) {
            // this will call exit()
            Response::sendError($request, 'Downloads by page, series or author are disabled in config');
        }
        if (Config::get('fetch_protect') == '1') {
            session_start();
            if (!isset($_SESSION['connected'])) {
                // this will call exit()
                Response::notFound($request);
            }
        }

        // create empty file response to start with!?
        $response = new FileResponse();

        $zipper = new Zipper($request, $response);

        if ($zipper->isValidForDownload()) {
            $sendHeaders = headers_sent() ? false : true;
            // disable nginx buffering by default
            if ($sendHeaders) {
                header('X-Accel-Buffering: no');
            }
            $zipper->download(null, $sendHeaders);
        } else {
            Response::sendError($request, "Invalid download: " . $zipper->getMessage());
        }
    }
}
