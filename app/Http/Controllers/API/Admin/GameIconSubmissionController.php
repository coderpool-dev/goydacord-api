<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Integrations\GameIcon;
use App\Models\Integrations\GameIconSubmission;
use App\Services\Integrations\GameIconService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GameIconSubmissionController extends Controller
{
    public function __construct(private readonly GameIconService $icons) {}

    public function index(): JsonResponse
    {
        $submissions = GameIconSubmission::query()
            ->with('uploader:id,name,login')
            ->where('status', 'pending')
            ->oldest()
            ->paginate(30);
        $published = GameIcon::query()->whereIn('slug', $submissions->getCollection()->pluck('slug'))->get()->keyBy('slug');

        return $this->successResponse('Заявки на иконки игр', [
            'submissions' => $submissions->getCollection()->map(fn (GameIconSubmission $submission) => [
                'id' => $submission->id,
                'name' => $submission->name,
                'slug' => $submission->slug,
                'source' => $submission->source,
                'created_at' => $submission->created_at,
                'uploader' => $submission->uploader ? [
                    'id' => $submission->uploader->id,
                    'name' => $submission->uploader->name,
                    'login' => $submission->uploader->login,
                ] : null,
                'current_icon_url' => $published->get($submission->slug)?->iconUrl(),
            ])->values(),
            'pagination' => [
                'current_page' => $submissions->currentPage(),
                'last_page' => $submissions->lastPage(),
                'total' => $submissions->total(),
            ],
        ]);
    }

    public function preview(GameIconSubmission $submission): BinaryFileResponse
    {
        abort_unless($submission->status === 'pending' && Storage::disk('local')->exists($submission->file), 404);

        return response()->file(Storage::disk('local')->path($submission->file), [
            'Content-Type' => match (pathinfo($submission->file, PATHINFO_EXTENSION)) {
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'webp' => 'image/webp',
                default => 'application/octet-stream',
            },
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    public function approve(Request $request, GameIconSubmission $submission): JsonResponse
    {
        $validated = $request->validate(['source_priority' => ['required', 'integer', Rule::in([1, 2, 3])]]);
        $icon = $this->icons->approve($submission, $request->user(), (int) $validated['source_priority']);

        return $this->successResponse('Иконка опубликована', ['icon' => [
            'slug' => $icon->slug,
            'name' => $icon->name,
            'icon_url' => $icon->iconUrl(),
        ]]);
    }

    public function reject(Request $request, GameIconSubmission $submission): JsonResponse
    {
        $this->icons->reject($submission, $request->user());

        return $this->successResponse('Заявка отклонена');
    }
}
