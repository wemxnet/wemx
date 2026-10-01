@extends('admin::layouts.wrapper', [
    'activePage' => 'images',
])

@section('title', 'Images')

@php
    use Illuminate\Support\Facades\File;

    $path = public_path('assets/common/img');
    $files = File::files($path);

    $images = collect($files)->filter(function ($file) {
        return in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
    })->map(function ($file) {
        return asset('assets/common/img/' . $file->getFilename());
    })->values(); // reindex keys
@endphp

@section('content')
    <div class="card mb-3">
        <form action="{{ route('admin.images.upload') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="card-body">
                <div class="mb-3">
                    <x-admin::form.label label="Upload Image" />
                    <x-admin::form.input
                        type="file"
                        name="image"
                        accept="image/*"
                        class="mb-2"
                        required
                    />
                    @error('image')
                        <x-admin::form.error text="{{ $message }}" class="mb-2" />
                    @enderror
                </div>
                <div>
                    <x-admin::form.label label="File Name (Optional)" />
                    <x-admin::form.input
                        type="text"
                        name="file_name"
                        placeholder="example.png"
                        class="mb-2"
                    />
                    @error('file_name')
                        <x-admin::form.error text="{{ $message }}" class="mb-2" />
                    @else
                        <x-admin::form.description description="Upload an image to the public/assets/common/img directory. You can then use the image URL in various parts of the application." />
                    @enderror
                </div>
                <button type="submit" class="btn btn-primary mt-3">Upload Image</button>
            </div>
        </form>
    </div>
    <div class="row">
        @foreach($images as $image)
            <div class="col-6 col-md-4 col-lg-3 col-xl-3 mb-3">
                <div class="card">
                    <img src="{{ $image }}" class="card-img-top" alt="Image">
                    <div class="card-body p-2 text-center">
                        <x-admin::form.input
                            type="text"
                            class="mb-2"
                            label="Image URL"
                            :value="$image"
                            readonly
                            onclick="this.select()"
                        />
                        <a href="{{ $image }}" target="_blank" class="btn btn-primary">View Full Size</a>
                        <a href="#" class="btn btn-secondary" onclick="navigator.clipboard.writeText('{{ $image }}'); this.innerText='Copied!'; setTimeout(() => { this.innerText='Copy URL'; }, 2000); return false;">
                            Copy URL
                        </a>
                        <form action="{{ route('admin.images.delete', ['file_name' => basename($image)]) }}" method="POST" style="display:inline-block;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Sicher löschen?')">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
