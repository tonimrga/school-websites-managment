@props(['method', 'scope' => null])

{{--
    A drag-and-drop list for reorder pages. After each drop it calls the
    Livewire method with the new order of item keys (preceded by $scope when
    given, e.g. a category id). Items can't be dragged between lists.
--}}
<ul
    x-sortable
    x-on:end.stop="$wire.{{ $method }}({{ $scope !== null ? (int) $scope.', ' : '' }}$event.target.sortable.toArray())"
    data-sortable-animation-duration="300"
    style="display: grid; gap: 0.5rem; margin: 0; padding: 0; list-style: none;"
>
    {{ $slot }}
</ul>
