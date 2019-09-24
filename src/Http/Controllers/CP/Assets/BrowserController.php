<?php

namespace Statamic\Http\Controllers\CP\Assets;

use Statamic\Facades\Asset;
use Statamic\Support\Str;
use Statamic\Facades\Action;
use Illuminate\Http\Request;
use Statamic\Facades\AssetContainer;
use Illuminate\Http\Resources\Json\Resource;
use Statamic\Http\Controllers\CP\CpController;
use Illuminate\Pagination\LengthAwarePaginator;

class BrowserController extends CpController
{
    public function index()
    {
        $containers = AssetContainer::all();

        if ($containers->isEmpty()) {
            return view('statamic::assets.index');
        }

        // TODO: Filter out unauthorized containers
        // TODO: Handle no authorized containers

        return redirect()
            ->cpRoute('assets.browse.show', $containers->first()->handle());
    }

    public function show($containerHandle, $path = '/')
    {
        // TODO: Auth

        $container = AssetContainer::find($containerHandle);

        abort_unless($container, 404);

        return view('statamic::assets.browse', [
            'container' => $this->toContainerArray($container),
            'folder' => $path,
        ]);
    }

    public function edit($containerHandle, $path)
    {
        // TODO: Auth

        $container = AssetContainer::find($containerHandle);
        $asset = Asset::find("{$containerHandle}::{$path}");

        abort_unless($container && $asset, 404);

        return view('statamic::assets.browse', [
            'container' => $this->toContainerArray($container),
            'folder' => $asset->folder(),
            'editing' => $asset->id(),
        ]);
    }

    public function folder(Request $request, $container, $path = '/')
    {
        // TODO: Auth

        $container = AssetContainer::find($container);

        if (! $container) {
            return $this->pageNotFound();
        }

        $paginator = $container
            ->queryAssets()
            ->where('folder', $path)
            ->orderBy($request->sort, $request->order)
            ->paginate(30);

        $this->supplementAssetsForDisplay($paginator->getCollection());

        return Resource::collection($paginator)->additional(['meta' => [
            'container' => $this->toContainerArray($container),
            'folders' => $container->assetFolders($path)->values()->each->withActions()->toArray(),
            'folder' => $container->assetFolder($path)->withActions()->toArray(),
            'actionUrl' => cp_route('assets.actions'),
            'folderActionUrl' => cp_route('assets.folders.actions', $container->id()),
        ]]);
    }

    public function search(Request $request, $container)
    {
        // TODO: Auth

        $container = AssetContainer::find($container);

        if (! $container) {
            return $this->pageNotFound();
        }

        $query = $container->hasSearchIndex()
            ? $container->searchIndex()->ensureExists()->search($request->search)
            : $container->queryAssets()->where('path', 'like', '%'.$request->search.'%');

        $paginator = $query->paginate(30);

        $this->supplementAssetsForDisplay($paginator->getCollection());

        return Resource::collection($paginator)->additional(['meta' => [
            'container' => $this->toContainerArray($container),
            'folders' => [],
            'folder' => $container->assetFolder('/')->toArray()
        ]]);
    }

    private function supplementAssetsForDisplay($assets)
    {
        foreach ($assets as &$asset) {
            // Add thumbnails to all image assets.
            if ($asset->isImage()) {
                $asset->setSupplement('thumbnail', $this->thumbnail($asset, 'small'));
                $asset->setSupplement('toenail', $this->thumbnail($asset, 'large'));
            }

            // Set some values for better listing formatting.
            $asset->setSupplement('size_formatted', Str::fileSizeForHumans($asset->size(), 0));
            $asset->setSupplement('last_modified_formatted', $asset->lastModified()->format(config('statamic.cp.date_format')));
            $asset->setSupplement('last_modified_relative', $asset->lastModified()->diffForHumans());

            // Pass authorized actions in with each asset.
            $asset->setSupplement('actions', Action::for('asset-browser', ['container' => $asset->container()->handle()], $asset));
        }

        return $assets;
    }

    private function thumbnail($asset, $preset = null)
    {
        return $asset->thumbnailUrl($preset);
    }

    private function toContainerArray($container)
    {
        return [
            'id' => $container->id(),
            'title' => $container->title(),
            'edit_url' => $container->editUrl()
        ];
    }
}