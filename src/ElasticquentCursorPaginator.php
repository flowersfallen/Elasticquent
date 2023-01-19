<?php


namespace Elasticquent;


use ArrayAccess;
use Countable;
use Illuminate\Contracts\Pagination\CursorPaginator as PaginatorContract;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\ForwardsCalls;
use Illuminate\Support\Traits\Tappable;
use IteratorAggregate;
use JsonSerializable;
use Illuminate\Pagination\AbstractCursorPaginator;
use Illuminate\Pagination\Paginator;
use Traversable;

use Illuminate\Pagination\CursorPaginator;

class ElasticquentCursorPaginator extends CursorPaginator
{

    /**
     * The paginator options.
     *
     * @var string
     */
    protected $itemSortKey;


    /**
     * Create a new paginator instance.
     *
     * @param  mixed  $items
     * @param  int  $perPage
     * @param  \Illuminate\Pagination\Cursor|null  $cursor
     * @return void
     */
    public function __construct($items, $perPage, $cursor, array $options = [])
    {
        $this->options = $options;

        foreach ($options as $key => $value) {
            $this->{$key} = $value;
        }

        $this->perPage = $perPage;
        $this->cursor = $cursor;
        $this->path = $this->path !== '/' ? rtrim($this->path, '/') : $this->path;
        

        $this->setItems($items);
    }


    /**
     * Get the cursor parameters for a given object.
     *
     * @param  \ArrayAccess|\stdClass  $item
     * @return array
     *
     * @throws \Exception
     */
    public function getParametersForItem($item)
    {
        return collect($this->parameters)
            ->flip()
            ->map(function ($_, $parameterName) use ($item) {
                return Arr::get($item, $this->itemSortKey.'.'.$_);
                throw new Exception('Only arrays and objects are supported when cursor paginating items.');
            })->toArray();
    }


}
