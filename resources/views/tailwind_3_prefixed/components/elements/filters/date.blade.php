<?php

use DefStudio\WiredTables\Elements\Filter;
use Illuminate\View\ComponentAttributeBag;

/** @var Filter $filter */
/** @var ComponentAttributeBag $attributes */
?>

@props(['filter', 'label' => true])

<div wire:key="wt-{{$this->id}}-filter-{{$filter->key()}}-wrapper" {{$attributes}}>
    @if($label)
        <label for="wt-{{$this->id}}-filter-{{$filter->key()}}"
               class="tw-block tw-font-medium tw-text-sm tw-text-gray-500"
        >
            {{$filter->name()}}
        </label>
    @endif
    <input id="wt-{{$this->id}}-filter-{{$filter->key()}}"
           wire:key="wt-{{$this->id}}-filter-{{$filter->key()}}"
           name="filterValues[{{$filter->key()}}]"
           wire:model.debounce="filterValues.{{$filter->key()}}"
           class="tw-block  tw-border-gray-300 tw-focus:border-indigo-300
                 tw-focus:ring tw-focus:ring-indigo-200 tw-focus:ring-opacity-50
                 tw-rounded-md tw-shadow-sm tw-px-2 tw-py-1 tw-w-full tw-min-w-[190px]"
           type="date"
    />
</div>

