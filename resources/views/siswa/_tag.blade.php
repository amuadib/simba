<div class="position-relative">
    <div id="tagBox" class="form-control d-flex flex-wrap gap-2" style="min-height:48px; cursor:text">

        @isset($data)
            @foreach ($data->tags as $tag)
                <span class="tag-chip bg-primary text-white" data-id="{{ $tag->id }}"
                    data-name="{{ strtolower($tag->nama) }}">
                    {{ $tag->nama }}
                    <button type="button" onclick="removeTag(this)">×</button>
                    <input type="hidden" name="tags[]" value="{{ $tag->id }}">
                </span>
            @endforeach
        @endisset

        <input type="text" id="tagInput" class="flex-grow-1 border-0" placeholder="Tambahkan tag" autocomplete="off"
            style="outline:none">
    </div>

    <div id="tagDropdown" class="list-group position-absolute w-100 d-none"
        style="z-index:1000; max-height:180px; overflow:auto"></div>
</div>
