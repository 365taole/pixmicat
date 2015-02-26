<?php

namespace Pixmicat\Webm;

use Pixmicat\PMCLibrary;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\Exception\RuntimeException;

class Webm {

    /**
     * 啟動時先檢查執行檔
     */
    public static function checkEnvironment() {
        global $FFMPEG_CONFIGS;

        if (!is_executable($FFMPEG_CONFIGS['ffmpeg.binaries']) || !is_executable($FFMPEG_CONFIGS['ffprobe.binaries'])) {
            if (defined('DEBUG')) {
                return FALSE;
            } else {
                \Pixmicat\error(\Pixmicat\_T('webm_executable_not_found'));
            }
        }
    }

    /**
     * 檢查檔案是否為WebM
     * @param string $filename
     * @return boolean|array
     * @throws \FFMpeg\Exception\InvalidArgumentException
     */
    public static function isWebm($filename) {
        try {
            if (is_file($filename)) {
                $ffprobe = self::getFFProbeInstance();
                
                // check format
                $format = $ffprobe->format($filename);
                if (strstr((string) $format->get('format_name'), 'webm') === FALSE) {
                    return FALSE;
                }
                
                // extract stream
                $stream = $ffprobe->streams($filename)->first();
                return [
                    'W' => (int) $stream->get('width'),
                    'H' => (int) $stream->get('height')
                ];
            }
        } catch (RuntimeException $e) {
            $this->runtimeException($e);
        }

        return FALSE;
    }

    /**
     * 產生縮圖
     * @global array $THUMB_SETTING
     * @param string $filename
     * @param string $destination
     * @param array $info returned from Webm::isWebm
     * @param integer $W width of thumbnail
     * @param integer $H height of thumbnail
     * @return array
     * @throws \FFMpeg\Exception\InvalidArgumentException
     */
    public static function createThumbnail($filename, $destination, array $info, $W, $H) {
        global $THUMB_SETTING;
        
        try {
            $ffmpeg = self::getFFMpegInstance();
            $video = $ffmpeg->open($filename);
            $video->frame(\FFMpeg\Coordinate\TimeCode::fromSeconds(0))
                    ->save($destination);
        } catch (\FFMpeg\Exception\RuntimeException $e) {
            $this->runtimeException($e);
        }

        $instThumb = PMCLibrary::getThumbInstance();
        $instThumb->setSourceConfig($destination, $info['W'], $info['H']);
        $instThumb->setThumbnailConfig($W, $H, $THUMB_SETTING);
        $instThumb->makeThumbnailtoFile($destination);
    }

    /**
     * Log and show error message
     * @param RuntimeException $e
     */
    private static function runtimeException(RuntimeException $e) {
        PMCLibrary::getLoggerInstance()->error("Message: %s\nTrace:\n%s", [$e->getMessage(), $e->getTraceAsString()]);
        \Pixmicat\error(_T('webm_exception'));
    }

    /**
     * @global array $FFMPEG_CONFIGS
     * @return FFMpeg
     */
    private static function getFFMpegInstance() {
        global $FFMPEG_CONFIGS;
        return FFMpeg::create($FFMPEG_CONFIGS);
    }

    /**
     * @global array $FFMPEG_CONFIGS
     * @return FFProbe
     */
    private static function getFFProbeInstance() {
        global $FFMPEG_CONFIGS;
        return FFProbe::create($FFMPEG_CONFIGS);
    }

}
