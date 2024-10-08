<?php

declare(strict_types=1);

namespace Rakoitde\Table;

use CodeIgniter\HTTP\IncomingRequest;

/**
 * This class describes a table.
 */
class Table
{
    use Traits\hasAccents;
    use Traits\hasActions;
    use Traits\hasBulkActions;
    use Traits\hasId;
    use Traits\hasClasses;
    use Traits\hasColors;
    use Traits\hasColumns;
    use Traits\hasColumnSelector;
    use Traits\hasConfig;
    use Traits\hasExporter;
    use Traits\hasFilters;
    use Traits\hasSummaries;
    use Traits\hasHeading;

    // use Traits\hasTHead;
    // use Traits\hasTBody;
    // use Traits\hasTFoot;
    use Traits\hasCaption;
    use Traits\hasModel;
    use Traits\hasName;
    use Traits\hasRows;
    use Traits\hasQuery;
    use Traits\hasPaginations;
    use Traits\hasExport;

    protected string $uri;
    protected IncomingRequest $request;
    protected array $entities;

    // protected array $___values;

    // protected array $___options;

    // protected bool $___sortable;

    // protected bool $___filterable;

    // protected array $___filtervalues;

    protected bool $isSmall             = false;
    protected bool $hasSearchableFields = false;

    /**
     * Sets the uri
     *
     * @param string $uri The uri
     */
    public function uri(string $uri): self
    {
        $this->uri = $uri;

        return $this;
    }

    /**
     * Gets the uri.
     *
     * @return string The uri.
     */
    public function getUri(string $suburi = ''): string
    {
        $uri = $this->uri;
        $uri .= str_ends_with($this->uri, '/') || str_starts_with($this->uri, '/') ? '' : '/';
        $uri .= $suburi;

        return $uri;
    }

    /**
     * Gets the uri.
     *
     * @return string The uri.
     */
    public function getUriWithQuery(string $suburi = ''): string
    {
        $uri = service('uri', base_url($this->getUri($suburi)));
        $uri->setPath($suburi);
        $uri->setQueryArray(['table' => request()->getGet('table')]);

        return (string) $uri;
    }

    /**
     * Set table size small. Default is true.
     *
     * @return self this table
     */
    public function small(bool $isSmall = true): self
    {
        $this->isSmall = $isSmall;

        if ($isSmall) {
            $this->addClass('table-sm');
        }

        return $this;
    }

    /**
     * check if table size is small
     *
     * @return bool true, if table size is small
     */
    public function isSmall(): bool
    {
        return $this->isSmall;
    }

    /**
     * { function_description }
     *
     * @param array $options The options
     *
     * @return self   ( description_of_the_return_value )
     */
    public function ___Options(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Mark all colums as sortable
     *
     * @param bool $sortable The sortable
     */
    public function ___Sort(bool $sortable = true): self
    {
        $this->sortable = $sortable;

        return $this;
    }

    /**
     * Mark all colums as filterable
     *
     * @param bool $filterable The filterable
     */
    public function ___Filter(bool $filterable = true): self
    {
        $this->filterable = $filterable;

        return $this;
    }

    /**
     * Returns the form id for html elements
     *
     * @return string form id
     */
    public function getFormId(): string
    {
        return 'form_' . $this->id;
    }

    /**
     * Returns the form id for html elements
     *
     * @return string form id
     */
    public function getFormIdAttribute(): string
    {
        return 'form="' . $this->getFormId() . '"';
    }

    /**
     * Returns the search name which can be used in the name tag of an HTML element
     *
     * @return string name of search variable
     */
    public function getSearchName(): string
    {
        return 'table[' . $this->getId() . '][globalsearch]';
    }

    /**
     * Returns the search string from get request
     *
     * @return string search string
     */
    public function getSearchString(): string
    {
        $search = $this->getRequestVars('globalsearch');

        return $search ?? '';
    }

    /**
     * Returns the get request of table vars
     *
     * @param string|null $subPath table[tableid][$subPath]
     *
     * @return [type]           [description]
     */
    public function getRequestVars(?string $subPath = null)
    {
        if (isset($subPath)) {
            return request()->getGet('table')[$this->getId()][$subPath] ?? null;
        }

        return request()->getGet('table')[$this->getId()] ?? null;
    }

    /**
     * if there are searchable columns, the model will be searched by the affected fields
     */
    public function searchModel()
    {
        if ($this->hasSearchableFields) {
            $search = $this->getSearchString();

            if ($search > '') {
                $this->model->groupStart();

                foreach ($this->getColumns() as $column) {
                    if ($column->isSearchable()) {
                        $this->model->orLike($column->getField(), str_replace('*', '%', $search));
                    }
                }
                $this->model->groupEnd();
            }
        }
    }

    /**
     * if there are sortable columns, the model will be sorted by the affected fields
     */
    public function sortModel()
    {
        foreach ($this->getColumns() as $column) {
            if (! $column->isSortable() || empty($column->getDirection())) {
                continue;
            }
            $this->model->orderBy($column->getFieldname(), $column->getDirection());
        }
    }

    /**
     * Gets the entities.
     *
     * @return array The entities.
     */
    public function getEntities()
    {
        if (! $this->hasColumns()) {
            return [];
        }

        return $this->entities ?? $this->createEntities();
    }

    /**
     * Create the array of all entities after run a custom query,
     * set filter, search and sort the data,
     * when paginated, the data is limited
     *
     * @param bool|bool $asArray           return entities as array, not as entity
     * @param bool|bool $disablePagination disablePagination for export as example
     *
     * @return array The created entities.
     */
    public function createEntities(bool $asArray = false, bool $disablePagination = false): array
    {
        if ($asArray === true) {
            $this->model->asArray();
        }

        $this->runQuery($this->model);

        $this->filterModel($this->model);

        $this->searchModel($this->model);

        $this->sortModel();

        $this->lastCompiledSelect = $this->model->builder->getCompiledSelect(false);

        if ($this->hasPagination() && ! $disablePagination) {
            $totalRows = $this->model->countAllResults(false);

            $this->getPagination()->setTotalRows($totalRows);

            $this->entities = $this->model->findAll(
                $this->getPagination()->getRowsPerPage(),
                $this->getPagination()->getRowsOffset(),
            );
        } else {
            $this->entities = $this->model->findAll();
        }

        return $this->entities;
    }

    public function getDebug()
    {
        d(
            // $this,
            // $this->getVisibleColumnArray(),
            // $this->getColumns2(),
            // $this->getColumns2(isVisible:true),
        );
    }

    public static function make(?string $id = null)
    {
        $static = new static();
        if (isset($id)) {
            $static->id($id);
        }

        return $static;
    }

    private function init()
    {
        $this->addClass('table');
        // $this->sortable   = $this->config->sortable;
        // $this->filterable = $this->config->filterable;
        // $this->perpage    = $this->config->perpage;
        if ($this->config->small) {
            $this->small();
        }
        if ($this->config->striped) {
            $this->striped();
        }
        if ($this->config->hover) {
            $this->hover();
        }
    }

    public function __toString()
    {
        if (! $this->hasModel()) {
            return 'Not Model';
        }

        $data = [
            'Table'      => $this,
            'primaryKey' => $this->getModel()->primaryKey,
        ];

        return view($this->config->views['table'], $data);
    }

    /**
     * Constructs a new instance.
     *
     * @param string $modelname The modelname
     */
    public function __construct()
    {
        // load config
        $this->config(config('Table'));

        $this->init();

        // set defaults
        $this->template = config('Rakoitde\Table\Config\\' . $this->config->templatename);

        // set uri
        $this->uri(current_url(true)->getPath());
    }
}