<?php namespace Elasticquent;

use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Arr;

class ElasticquentSeoPaginator extends Paginator
{
    /**
     * Create a new paginator instance.
     *
     * @param  mixed  $items
     * @param  mixed  $hits
     * @param  int  $total
     * @param  int  $perPage
     * @param  int|null  $currentPage
     * @param  array  $options (path, query, fragment, pageName)
     */
    public function __construct($items, $hits, $total, $perPage, $currentPage = null, array $options = [])
    {
        foreach ($options as $key => $value) {
            $this->{$key} = $value;
        }
        $this->total = $total;
        $this->perPage = $perPage;
        $this->lastPage = (int) ceil($total / $perPage);
        $this->currentPage = $this->setCurrentPage($currentPage, $this->lastPage);


        $this->path = $this->path != '/' ? rtrim($this->path, '/') . '/' :  $this->path;
        $this->items = $items instanceof Collection ? $items : Collection::make($items);
        $this->hits = $hits;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'total'         => $this->total(),
            'per_page'      => $this->perPage(),
            'current_page'  => $this->currentPage(),
            'last_page'     => $this->lastPage(),
            'next_page_url' => $this->nextPageUrl(),
            'prev_page_url' => $this->previousPageUrl(),
            'from'          => $this->firstItem(),
            'to'            => $this->lastItem(),
            'hits'          => $this->hits,
            'data'          => $this->items->toArray(),
        ];
    }

    public function url($page)
    {
        if ($page <= 0) {
            $page = 1;
        }
        $parameters = [];
        if($page == 1){
            if (count($this->query) > 0 && Arr::has($this->query, $this->pageName)) {
                $parameters = Arr::except($this->query, $this->pageName);
            }

        }else{
            // If we have any extra query string key / value pairs that need to be added
            // onto the URL, we will put them in query string form and then attach it
            // to the URL. This allows for extra information like sortings storage.
            $parameters = [$this->pageName => $page];

            if (count($this->query) > 0) {
                $parameters = array_merge($this->query, $parameters);
            }
        }

        if($parameters){
            return $this->path()
                .(str_contains($this->path(), '?') ? '&' : '?')
                .Arr::query($parameters)
                .$this->buildFragment();
        }else{
            return $this->path()
                .$this->buildFragment();
        }

    }
}
