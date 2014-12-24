<?php

class Metric extends Eloquent {

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = '_call_metrics';

    /**
     * Register new API call
     *
     * @param string $endpoint
     * @param string $called_from
     * @param integer $status
     * @param string $message
     */
    public static function registerCall($endpoint = '', $called_from = '', $status = 1, $message = '')
    {
        $today = date('Y-m-d 00:00:00');
        if ($endpoint != '' && $called_from != '') {
            $existing_metric = Metric::where('endpoint', '=', $endpoint)
                ->where('called_from', '=', $called_from)
                ->where('called_at', '>', $today);
            if ($existing_metric->count() > 0) {
                $metric = $existing_metric->first();
                $metric->times_called++;
                $metric->status = $status;
                $metric->message = $message;
                $metric->update();
            } else {
                $metric = new Metric();
                $metric->endpoint = $endpoint;
                $metric->times_called = 1;
                $metric->called_at = date('Y-m-d H:i:s');
                $metric->called_from = $called_from;
                $metric->status = $status;
                $metric->message = $message;
                $metric->save();
            }
        }
    }
}