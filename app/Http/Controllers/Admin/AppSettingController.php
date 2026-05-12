<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\Settings\UpdateAppSettingRequest;
use App\Models\AppSetting;
use App\Services\Admin\AdminActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use JsonException;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AppSettingController extends BaseAdminController
{
    public function __construct(protected AdminActivityLogService $activityLogService)
    {
    }

    public function index(Request $request): View
    {
        $settings = AppSetting::query()
            ->when($request->string('q')->toString() !== '', function ($query) use ($request): void {
                $search = $request->string('q')->toString();
                $query->where('key', 'like', '%'.$search.'%')
                    ->orWhere('group_name', 'like', '%'.$search.'%');
            })
            ->orderBy('group_name')
            ->orderBy('key')
            ->paginate(20)
            ->withQueryString();

        return view('admin.settings.index', [
            'settings' => $settings,
            'filters' => $request->only(['q']),
        ]);
    }

    public function update(UpdateAppSettingRequest $request, AppSetting $appSetting): RedirectResponse
    {
        $normalizedValue = $this->normalizeValue(
            type: $request->validated('value_type'),
            value: $request->validated('value'),
        );

        $appSetting->forceFill([
            'value' => [
                'value' => $normalizedValue,
            ],
        ])->save();

        $this->activityLogService->log(
            admin: $this->admin(),
            action: 'app_setting.updated',
            description: 'Updated app setting "'.$appSetting->key.'".',
            target: $appSetting->key,
            metadata: [
                'group_name' => $appSetting->group_name,
                'value_type' => $request->validated('value_type'),
                'value' => $normalizedValue,
            ],
        );

        return redirect()
            ->back()
            ->with('status', 'App setting updated successfully.');
    }

    protected function normalizeValue(string $type, string $value): mixed
    {
        return match ($type) {
            'string' => $value,
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? throw ValidationException::withMessages([
                'value' => ['Boolean values must be true, false, 1, or 0.'],
            ]),
            'json' => $this->decodeJsonValue($value),
        };
    }

    protected function decodeJsonValue(string $value): array
    {
        try {
            /** @var array $decoded */
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

            return $decoded;
        } catch (JsonException) {
            throw ValidationException::withMessages([
                'value' => ['The JSON value could not be parsed.'],
            ]);
        }
    }
}
