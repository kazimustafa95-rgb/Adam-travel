<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\Cms\UpdateCmsPageRequest;
use App\Models\CmsPage;
use App\Services\Admin\AdminActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CmsPageController extends BaseAdminController
{
    public function __construct(protected AdminActivityLogService $activityLogService)
    {
    }

    public function index(Request $request): View
    {
        $pages = CmsPage::query()
            ->when($request->string('q')->toString() !== '', function ($query) use ($request): void {
                $search = $request->string('q')->toString();
                $query->where('title', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%');
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.cms.index', [
            'pages' => $pages,
            'filters' => $request->only(['q']),
        ]);
    }

    public function edit(CmsPage $cmsPage): View
    {
        return view('admin.cms.edit', [
            'page' => $cmsPage,
        ]);
    }

    public function update(UpdateCmsPageRequest $request, CmsPage $cmsPage): RedirectResponse
    {
        $isPublished = $request->boolean('is_published');

        $cmsPage->fill([
            'title' => $request->validated('title'),
            'content' => $request->validated('content'),
            'is_published' => $isPublished,
            'published_at' => $isPublished ? ($cmsPage->published_at ?? now()) : null,
        ])->save();

        $this->activityLogService->log(
            admin: $this->admin(),
            action: 'cms_page.updated',
            description: 'Updated CMS page "'.$cmsPage->title.'".',
            target: $cmsPage,
            metadata: [
                'is_published' => $isPublished,
            ],
        );

        return redirect()
            ->route('admin.cms-pages.edit', $cmsPage)
            ->with('status', 'CMS page updated successfully.');
    }
}
