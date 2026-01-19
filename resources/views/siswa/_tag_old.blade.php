<div class="col-12">
    <label class="form-label d-flex justify-content-between">
        Tag Siswa
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="showTagInput()">+ Tag</button>
    </label>

    <div id="tagInput" class="input-group d-none mb-2">
        <input type="text" id="newTag" class="form-control" placeholder="Nama tag">
        <button class="btn btn-success" type="button" onclick="createTag()">✔</button>
        <button class="btn btn-outline-secondary" type="button" onclick="hideTagInput()">✖</button>
    </div>

    <div id="tagList" class="d-flex flex-wrap gap-2">
        @foreach ($tags as $tag)
            <span class="badge rounded-pill bg-light text-dark tag-item border" data-id="{{ $tag->id }}">
                <input type="checkbox" name="tags[]" value="{{ $tag->id }}" class="form-check-input me-1"
                    {{ isset($data) && $data->tags->contains($tag->id) ? 'checked' : '' }}>
                <span class="tag-name">{{ $tag->nama }}</span>

                <span class="text-muted tag-actions ms-1">
                    <a href="#" onclick="editTag(event, {{ $tag->id }})">✏️</a>
                    <a href="#" onclick="deleteTag(event, {{ $tag->id }})">❌</a>
                </span>
            </span>
        @endforeach
    </div>
</div>
