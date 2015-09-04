<?php
class UNL_MediaHub_MediaTextTrackList extends UNL_MediaHub_List
{
    public $options = array(
        'orderby' => 'datecreated',
        'order'   => 'DESC',
        'page'    => 0,
        'limit'   => -1,
        'additional_filters' => array(),
    );

    public $tables = 'UNL_MediaHub_MediaTextTrack tt';

    public function setUpFilter()
    {
        if (isset($this->options['media_id'])
            && !empty($this->options['media_id'])) {
            $this->options['filter'] = new UNL_MediaHub_MediaTextTrackList_Filter_ByMedia($this->options['q']);
        }
    }
    
    function setOrderBy(Doctrine_Query &$query)
    {
        $query->orderby('tt.'.$this->options['orderby'].' '.$this->options['order']);
    }
}