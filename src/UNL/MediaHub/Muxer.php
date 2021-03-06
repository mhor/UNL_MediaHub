<?php

class UNL_MediaHub_Muxer
{
    protected $media;
    
    public static $ffmpeg_path = false;
    
    function __construct(UNL_MediaHub_Media $media)
    {
        $this->media = $media;
    }
    
    public function mux()
    {
        if (!$this->media->getLocalFileName()) {
            //Not a local file, so we can't mux it
            return false;
        }

        if (!$this->media->isVideo()) {
            //Can't mux non-videos
            return false;
        }

        $track = UNL_MediaHub_MediaTextTrack::getById($this->media->media_text_tracks_id);

        if (!$track) {
            //No track found, remove any subtitles from the video
            return $this->ffmpegMuxVideo($this->media->getLocalFileName(), array());
        }

        $files = $track->getFiles();

        if (empty($files->items)) {
            //Track track actually has no files... remove any existing ones.
            return $this->ffmpegMuxVideo($this->media->getLocalFileName(), array());
        }

        $srt_files = array();

        foreach ($files->items as $file) {
            /**
             * @var $file UNL_MediaHub_MediaTextTrackFile
             */

            if (UNL_MediaHub_MediaTextTrackFile::FORMAT_VTT != $file->format) {
                //Only use VTT (as that is what is currently created by default).
                //There is currently no case where an srt will be created. This is just a failsafe.
                continue;
            }
            
            $tmp_srt_file = UNL_MediaHub::getRootDir() . '/tmp/' . $this->media->id . '_' . $file->language . '.srt';

            //Convert the webvtt format to srt (ffmpeg require srt)
            $vtt = new Captioning\Format\WebvttFile();
            $vtt->loadFromString(trim($file->file_contents));
            $srt = $vtt->convertTo('subrip');

            $srt->save($tmp_srt_file);

            //The language is stored in the database as iso639-1
            //ffmpeg requires iso639-2, so we need to convert it.
            $adapter = new \Conversio\Adapter\LanguageCode();
            $options = new \Conversio\Adapter\Options\LanguageCodeOptions();
            $options->setOutput('iso639-2/b');
            $converter = new \Conversio\Conversion($adapter);
            $converter->setAdapterOptions($options);

            $srt_files[] = array(
                'language' => $converter->filter($file->language),
                'file' => $tmp_srt_file,
            );
        }

        $result = $this->ffmpegMuxVideo($this->media->getLocalFileName(), $srt_files);
        
        return $result;
    }

    protected function ffmpegMuxVideo($video_file, array $subtitles) {
        $local_file_name = $this->media->getLocalFileName();
        $local_file_name_extension = pathinfo($local_file_name, PATHINFO_EXTENSION);
        $tmp_media_file = UNL_MediaHub::getRootDir() . '/tmp/' . $this->media->id . '.' . $local_file_name_extension;
        
        //This should copy the video and audio tracks while replacing the subtitle tracks. Thus, old subtitles will be removed.
        $command = 'nice ' . UNL_MediaHub::getFfmpegPath();
        $command .= ' -y'; //overwrite output files
        $command .= ' -i ' . $video_file; //Video input is test.mp4
        foreach ($subtitles as $subtitle) {
            $command .= ' -f srt'; //Caption format is .srt
            $command .= ' -i ' . $subtitle['file']; //Second input is the caption file
        }
        $command .= ' -c:v copy'; //copy video
        $command .= ' -c:a copy'; //copy audio
        if (empty($subtitles)) {
            $command .= ' -sn'; //Remove any existing subtitles
        } else {
            $command .= ' -c:s mov_text'; //Create a move_text subtitle
        }
        $command .= ' -map 0:v'; //Map the video to the output
        $command .= ' -map 0:a'; //Map the audio to the output
        $command .= ' -map_metadata:c -1'; //strip chapter information
        foreach ($subtitles as $index=>$subtitle) {
            //Figure out s:s:0 syntax
            $command .= ' -metadata:s:s:' . $index . ' language=' . $subtitle['language']; //set subtitle language to eng
            $command .= ' -map ' . ($index+1) . ':0'; //Map all subtitles to the output
        }
        $command .= ' ' . $tmp_media_file; //Output file
        $command .= ' 2>&1'; //Always redirect output to the standard output stream
        
        //Should exit with a zero status
        exec($command, $output, $result);
        
        //cleanup
        foreach ($subtitles as $subtitle) {
            unlink($subtitle['file']);
        }
        
        if ($result !== 0) {
            //Log failed muxings
            $error = '----------' . PHP_EOL;
            $error .= date('c') . ' -- mid='.$this->media->id . ' -- exit status: ' . $result . PHP_EOL;
            $error .= $command . PHP_EOL;
            $error .= '---' . PHP_EOL;
            $error .= implode(PHP_EOL, $output);
            $log_file = UNL_MediaHub::getRootDir() . '/tmp/mux_error_log.log';
            file_put_contents($log_file, $error, FILE_APPEND);
            
            unlink($tmp_media_file);
            
            throw new UNL_MediaHub_MuxerException('Unable to mux video');
        }
        
        //Move the new muxed file into place because it was a success
        rename($tmp_media_file, $video_file);
        
        return true;
    }
}
