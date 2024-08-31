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
use SebLucas\Cops\Input\Request;
use SebLucas\Cops\Calibre\Book;
use SebLucas\Cops\Calibre\Cover;
use SebLucas\Cops\Calibre\Data;
use SebLucas\Cops\Output\FileRenderer;
use SebLucas\Cops\Output\Zipper;

/**
 * Fetch book covers or files
 * URL format: fetch.php?id={bookId}&type={type}&data={idData}&view={viewOnly}
 *          or fetch.php?id={bookId}&thumb={thumb} for book cover thumbnails
 *          or fetch.php?id={bookId}&file={file} for extra data file for this book
 */
class FetchHandler extends BaseHandler
{
    public const HANDLER = "fetch";

    public static function getRoutes()
    {
        // check if the path starts with the endpoint param or not here
        return [
            // support custom pattern for route placeholders - see nikic/fast-route
            "/files/{db:\d+}/{id:\d+}/{file:.+}" => [static::PARAM => static::HANDLER],
            "/thumbs/{thumb}/{db:\d+}/{id:\d+}.jpg" => [static::PARAM => static::HANDLER],
            "/covers/{db:\d+}/{id:\d+}.jpg" => [static::PARAM => static::HANDLER],
            "/inline/{db:\d+}/{data:\d+}/{ignore}.{type}" => [static::PARAM => static::HANDLER, "view" => 1],
            "/fetch/{db:\d+}/{data:\d+}/{ignore}.{type}" => [static::PARAM => static::HANDLER],
            // @todo handle url rewriting if enabled separately - path parameters are different
            "/view/{data}/{db}/{ignore}.{type}" => [static::PARAM => static::HANDLER, "view" => 1],
            "/view/{data}/{ignore}.{type}" => [static::PARAM => static::HANDLER, "view" => 1],
            "/download/{data}/{db}/{ignore}.{type}" => [static::PARAM => static::HANDLER],
            "/download/{data}/{ignore}.{type}" => [static::PARAM => static::HANDLER],
        ];
    }

    /**
     * Summary of handle
     * @param Request $request
     * @return void
     */
    public function handle($request)
    {
        if (Config::get('fetch_protect') == '1') {
            session_start();
            if (!isset($_SESSION['connected'])) {
                // this will call exit()
                $request->notFound();
            }
        }
        // clean output buffers before sending the ebook data do avoid high memory usage on big ebooks (ie. comic books)
        if (ob_get_length() !== false && $request->getHandler() !== 'phpunit') {
            ob_end_clean();
        }

        $bookId   = $request->getId();
        $type     = $request->get('type', 'jpg');
        $idData   = $request->getId('data');
        $viewOnly = $request->get('view', false);
        $database = $request->database();
        $file     = $request->get('file');

        if (is_null($bookId)) {
            $book = Book::getBookByDataId($idData, $database);
        } else {
            $book = Book::getBookById($bookId, $database);
        }

        if (!$book) {
            // this will call exit()
            $request->notFound();
        }

        if (!empty($file)) {
            if ($file == 'zipped') {
                // zip all extra files and send back
                $this->zipExtraFiles($request, $book);
                return;
            }
            $this->sendExtraFile($request, $book, $file);
            return;
        }

        // -DC- Add png type
        if (in_array($type, ['jpg', 'png'])) {
            $this->sendThumbnail($request, $book, $type);
            return;
        }

        if (!$viewOnly && $type == 'epub' && Config::get('update_epub-metadata')) {
            $this->sendUpdatedEpub($request, $book, $idData);
            return;
        }

        $data = $book->getDataById($idData);
        // absolute path for single DB in PHP app here - cfr. internal dir for X-Accel-Redirect with Nginx
        $file = $book->getFilePath($type, $idData);

        if ($viewOnly) {
            FileRenderer::sendFile($file, '', $data->getMimeType());
            return;
        }

        if ($type == 'epub' && Config::get('provide_kepub') == '1'  && preg_match('/Kobo/', $request->agent())) {
            // run kepubify on original Epub file and send converted tmpfile
            if (!empty(Config::get('kepubify_path'))) {
                $kepubFile = $book->runKepubify($file, $data->getUpdatedFilenameKepub());
                if (empty($kepubFile)) {
                    echo 'Error: failed to convert epub file';
                }
                return;
            }
            // provide kepub in name only (without update of opf properties for cover-image in Epub)
            FileRenderer::sendFile($file, basename($data->getUpdatedFilenameKepub()), $data->getMimeType());
        }

        FileRenderer::sendFile($file, basename($file), $data->getMimeType());
    }

    /**
     * Summary of zipExtraFiles
     * @param Request $request
     * @param Book $book
     * @return void
     */
    public function zipExtraFiles($request, $book)
    {
        $zipper = new Zipper($request);

        if ($zipper->isValidForExtraFiles($book)) {
            // disable nginx buffering by default
            header('X-Accel-Buffering: no');
            $zipper->download();
        } else {
            echo "Invalid zipped: " . $zipper->getMessage();
        }
    }

    /**
     * Summary of sendExtraFile
     * @param Request $request
     * @param Book $book
     * @param string $file
     * @return void
     */
    public function sendExtraFile($request, $book, $file)
    {
        $extraFiles = $book->getExtraFiles();
        if (!in_array($file, $extraFiles)) {
            // this will call exit()
            $request->notFound();
        }
        // send back extra file
        $filepath = $book->path . '/' . Book::DATA_DIR_NAME . '/' . $file;
        if (!file_exists($filepath)) {
            // this will call exit()
            $request->notFound();
        }
        FileRenderer::sendFile($filepath, basename($filepath));
    }

    /**
     * Summary of sendThumbnail
     * @param Request $request
     * @param Book $book
     * @param string $type
     * @return void
     */
    public function sendThumbnail($request, $book, $type)
    {
        $file = $book->getCoverFilePath($type);
        if (empty($file) || !file_exists($file)) {
            // this will call exit()
            $request->notFound();
        }
        $cover = new Cover($book);
        $cover->sendThumbnail($request);
    }

    /**
     * Summary of sendUpdatedEpub
     * @param Request $request
     * @param Book $book
     * @param mixed $idData
     * @return void
     */
    public function sendUpdatedEpub($request, $book, $idData)
    {
        // update epub metadata + provide kepub if needed (with update of opf properties for cover-image in EPub)
        if (Config::get('provide_kepub') == '1'  && preg_match('/Kobo/', $request->agent())) {
            $book->updateForKepub = true;
        }
        // this will also use kepubify_path internally if defined
        $book->getUpdatedEpub($idData);
    }
}
