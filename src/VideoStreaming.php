<?php

namespace App;

class VideoStreaming
{
    private $path;
    private $handler;
    private $bufferSize = 102400;
    private $cursorStart = -1;
    private $cursorEnd = -1;
    private $fileSize = 0;
    private $fileType;

    const ALLOWED_FILE_TYPES = ["video/mp4"];

    public function __construct(string $path = null)
    {
        $this->path = $path;
    }

    private function getFileMimeType()
    {
        if (function_exists('finfo_file')) {
            $info = finfo_open(FILEINFO_MIME_TYPE);
            $type = finfo_file($info, $this->path);
            finfo_close($info);
            return $type;
        }
    }

    private function setFileType()
    {
        $fileType = $this->getFileMimeType();
        if (!in_array($fileType, self::ALLOWED_FILE_TYPES)) {
            header("HTTP/1.0 500 file not allowed");
            die();
        }
        $this->fileType = $fileType;
        return $this;
    }

    private function open()
    {
        if (!$this->handler = @fopen($this->path, 'rb')) {
            header("HTTP/1.0 404 file not found");
            die();
        }

        return $this;
    }

    private function setHeaderRangeNotSatisfiable()
    {
        header("HTTP/1.1 416 Requested Range Not Satisfiable");
        header("Content-Range: bytes $this->cursorStart-$this->cursorEnd/$this->fileSize");
        exit;
    }

    private function setCursorRange(string $rangeString)
    {
        $cursorStart = $this->cursorStart;
        $cursorEnd = $this->cursorEnd;
        list(, $range) = explode('=', $rangeString, 2);

        if (strpos($range, ',') !== false) {
            $this->setHeaderRangeNotSatisfiable();
        }

        if (strpos($range, '-') !== false) {
            $range = explode('-', $range);
            $cursorStart = (isset($range[0]) && is_numeric($range[0])) ? $range[0] : $cursorStart;
            $cursorEnd = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $cursorEnd;
        }

        $cursorEnd = ($cursorEnd > $this->cursorEnd) ? $this->cursorEnd : $cursorEnd;
        if ($cursorStart > $cursorEnd || $cursorStart > ($this->fileSize - 1) || $cursorEnd >= $this->fileSize) {
            $this->setHeaderRangeNotSatisfiable();
        }

        $this->cursorStart = $cursorStart;
        $this->cursorEnd = $cursorEnd;
        return $this;
    }

    private function setHeader()
    {
        ob_get_clean();
        header("Content-Type: " . $this->fileType);
        header("Cache-Control: max-age=2592000, public");
        header("Expire: " . gmdate('D, d M Y H:i:s', time() + 2592000) . "GMT");
        header("Last-Modified: " . gmdate('D, d M Y H:i:s', @filemtime($this->path)) . "GMT");

        $this->cursorStart = 0;
        $this->fileSize = filesize($this->path);
        $this->cursorEnd = ($this->fileSize - 1);
        $length = $this->fileSize;

        header("Accept-Ranges: 0-" . $this->cursorEnd);

        if (isset($_SERVER['HTTP_RANGE'])) {
            $this->setCursorRange($_SERVER['HTTP_RANGE']);
            $length = $this->cursorEnd - ($this->cursorStart - 1);
            fseek($this->handler, $this->cursorStart);
            header("HTTP/1.0 206 Partial Content");
            header("Content-Range: bytes $this->cursorStart-$this->cursorEnd/" . $this->fileSize);
        }

        header("Content-Length: " . $length);
        return $this;
    }

    private function streaming()
    {
        set_time_limit(0);
        $cursorStart = $this->cursorStart;
        while (!feof($this->handler) && $cursorStart < $this->cursorEnd) {
            $bufferSize = $this->bufferSize;
            if (($cursorStart + $bufferSize) > $this->cursorEnd) {
                $bufferSize = $this->cursorEnd - $cursorStart + 1;
            }
            $data = fread($this->handler, $bufferSize);
            echo $data;
            flush();
            $cursorStart += $bufferSize;
        }
        return $this;
    }

    private function end()
    {
        fclose($this->handler);
        exit;
    }

    public function display(string $path = null)
    {
        $this->path = $path ?: $this->path;
        $this->open()->setFileType()->setHeader()->streaming()->end();
    }
}
