<?php

namespace App\Services\SavedPlaces;

use App\Models\SavedPlace;
use App\Models\SavedPlaceCollection;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SavedPlaceCollectionService
{
    /**
     * @return Collection<int, SavedPlaceCollection>
     */
    public function listForUser(User $user): Collection
    {
        return SavedPlaceCollection::query()
            ->where('user_id', $user->id)
            ->withCount('savedPlaces')
            ->with([
                'savedPlaces' => fn ($query) => $query->with('location')->latest(),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(User $user, array $payload): SavedPlaceCollection
    {
        return DB::transaction(function () use ($user, $payload): SavedPlaceCollection {
            $collection = SavedPlaceCollection::query()->create([
                'user_id' => $user->id,
                'name' => $payload['name'],
                'description' => $payload['description'] ?? null,
                'color_hex' => $payload['color_hex'] ?? null,
                'sort_order' => $payload['sort_order'] ?? 0,
            ]);

            if (! empty($payload['saved_place_ids'])) {
                $savedPlaces = SavedPlace::query()
                    ->where('user_id', $user->id)
                    ->whereIn('id', $payload['saved_place_ids'])
                    ->get();

                if ($savedPlaces->count() !== count($payload['saved_place_ids'])) {
                    throw ValidationException::withMessages([
                        'saved_place_ids' => ['One or more selected saved places are not available.'],
                    ]);
                }

                foreach ($savedPlaces as $savedPlace) {
                    $this->assign($savedPlace, $collection);
                }
            }

            return $collection->fresh(['savedPlaces.location'])->loadCount('savedPlaces');
        });
    }

    public function assign(SavedPlace $savedPlace, ?SavedPlaceCollection $collection): SavedPlace
    {
        $savedPlace->fill([
            'saved_place_collection_id' => $collection?->id,
            'region_label' => $collection?->name,
            'version' => $savedPlace->version + 1,
        ]);
        $savedPlace->save();

        return $savedPlace->fresh(['location', 'savedPlaceCollection']);
    }
}
