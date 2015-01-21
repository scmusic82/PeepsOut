<?php
class Content extends Eloquent {

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'contents';

    public function setImagesAttribute($value = [])
    {
        $this->attributes['images'] = json_encode($value);
    }

    public function getImagesAttribute($value = '[]')
    {
        return (array)json_decode($value, true);
    }
}
