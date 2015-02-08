<?php
namespace Fubber\Comet;

class FileController extends \Fubber\Reactor\Controller {

    protected static $cache = array();

    public function get($request, $response) {

        $path = $request->getPath();
        $portion = substr($path, strlen($this->config['url']));

        $resolvedPath = realpath($this->config['root'].$portion);

        if (!$resolvedPath) {
            // Not found
            return Host::$instance->respondError(404, $request, $response);
        }

        if (strpos($resolvedPath, $this->config['root'])!==0) {
            // Outside of the folder
            return Host::$instance->respondError(403, $request, $response);
        }

        // Okay, we have a file we want to serve
        $this->serveFile($resolvedPath, $request, $response);
    }

    protected static function getMetaData($path) {
        static $cache = array();
        if (isset($cache[$path])) return $cache[$path];

        if (is_dir($path)) {
            $res = 'dir';
        } else {
            $fi = new \finfo(FILEINFO_NONE | FILEINFO_PRESERVE_ATIME);
            $res = array(
                'mimetype' => $fi->file($path, FILEINFO_MIME_TYPE),
                'charset' => $fi->file($path, FILEINFO_MIME_ENCODING),
                'filemtime' => filemtime($path),
                'filesize' => filesize($path),
                'pathinfo' => pathinfo($path),
            );
        }
        return $cache[$path] = $res;
    }

    public static $extensionMimeType = array(
        'js' => 'application/javascript',
        'json' => 'application/json',
        'css' => 'application/css',
        'gif' => 'image/gif',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'tif' => 'image/tiff',
        'tiff' => 'image/tiff',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'cur' => 'image/x-icon',
        'wbmp' => 'image/vnd.wap.wbmp',
        'webapp' => 'application/x-web-app-manifest+json',
        'manifest' => 'text/cache-manifest',
        'appcache' => 'text/cache-manifest',
        'doc' => 'application/msword',
        'xls' => 'application/vnd.ms-excel',
        'ppt' => 'application/vnd.ms-powerpoint',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        '3gp' => 'video/3gpp',
        'mp4' => 'video/mp4',
        'm4v' => 'video/mp4',
        'f4v' => 'video/mp4',
        'f4p' => 'video/mp4',
        'mpeg' => 'video/mpeg',
        'mpg' => 'video/mpeg',
        'ogv' => 'video/ogg',
        'mov' => 'video/mov',
        'webm' => 'video/webm',
        'flv' => 'video/x-flv',
        'mng' => 'video/x-mng',
        'asx' => 'video/x-ms-asf',
        'asf' => 'video/x-ms-asf',
        'wmv' => 'video/x-ms-wmv',
        'avi' => 'video/x-msvideo',
    );

    protected function serveFile($path, $request, $response) {

        // Get some meta data about the file
        $meta = self::getMetaData($path);

        if ($meta === 'dir') return FALSE;

        // Check If-Modified-Since
        $requestHeaders = $request->getHeaders();
        if(isset($requestHeaders['If-Modified-Since'])) {
            if($requestHeaders['If-Modified-Since'] == ($lastModified = gmdate('D, d M Y H:i:s', $meta['filemtime']))) {
                $response->writeHead(304, array('Last-Modified' => $lastModified));
                $response->end();
                return;
            }
        }

        $responseHeaders = array();

        if (isset($meta['pathinfo']['extension']) && isset(self::$extensionMimeType[strtolower($meta['pathinfo']['extension'])])) {
            // This extension has custom mime types
            $responseHeaders['Content-Type'] = self::$extensionMimeType[strtolower($meta['pathinfo']['extension'])];
        } elseif (strpos($meta['mimetype'], 'text/')===0) {
            $responseHeaders['Content-Type'] = $meta['mimetype'].'; charset='.$meta['charset'];
        } else {
            $responseHeaders['Content-Type'] = $meta['mimetype'];
        }

        // Modification time headers
        $responseHeaders['Last-Modified'] = gmdate('D, d M Y H:i:s', $meta['filemtime']);
        $responseHeaders['Content-Length'] = $meta['filesize'];

        $response->writeHead(200, $responseHeaders);
        $response->end(file_get_contents($path));
        return;


        if (!isset(self::$cache[$path])) {
            self::$cache[$path] = array(
                'filemtime' => filemtime($path),
                'filectime' => filectime($path),
                'filesize' => filesize($path),
            );
        }
        $fp = fopen($path, 'rb');
        
    }
}
