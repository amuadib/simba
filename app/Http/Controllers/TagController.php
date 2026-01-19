<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'nama' => 'required|string|max:30|unique:tag,nama',
        ]);

        $tag = Tag::create($data);

        return response()->json($tag);
    }

    public function update(Request $request, Tag $tag)
    {
        $data = $request->validate([
            'nama' => 'required|string|max:30|unique:tag,nama,' . $tag->id,
        ]);

        $tag->update($data);

        return response()->json($tag);
    }

    public function destroy(Tag $tag)
    {
        $tag->delete();

        return response()->json(['success' => true]);
    }
}
