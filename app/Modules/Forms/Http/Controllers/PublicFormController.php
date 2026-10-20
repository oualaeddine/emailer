<?php

namespace App\Modules\Forms\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Forms\Models\Form;
use App\Modules\Forms\Services\FormEmbedService;
use App\Modules\Forms\Services\FormSubmissionService;
use App\Modules\Suppression\Preferences\Services\PageViewBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Contracts\View\View;

/** docs/45 §45.7.4 — public `web:/f/{form}` (hosted page, submit, embed script, thank-you). */
class PublicFormController extends Controller
{
    public function __construct(
        private readonly FormSubmissionService $submissions,
        private readonly FormEmbedService $embed,
        private readonly PageViewBuilder $views,
    ) {}

    public function show(Form $form): View
    {
        $this->ensureActive($form);

        return view('forms.page', $this->data($form) + [
            'fields' => $this->embed->fields($form),
            'captcha' => $this->embed->captcha($form),
            'gdpr' => $form->gdpr ?? [],
            'action' => url('/f/'.$form->uuid),
            'thanks' => false,
        ]);
    }

    public function submit(Request $request, Form $form): JsonResponse|RedirectResponse
    {
        $this->ensureActive($form);

        $result = $this->submissions->submit($form, $request->all(), $request);

        if ($request->expectsJson()) {
            return response()->json(['data' => $result]);
        }
        if (! empty($result['redirect_url']) && $result['status'] === 'subscribed') {
            return redirect()->away($result['redirect_url']);
        }

        return redirect('/f/'.$form->uuid.'/merci')->with('form_message', $result['message']);
    }

    public function thanks(Request $request, Form $form): View
    {
        $this->ensureActive($form);

        return view('forms.page', $this->data($form) + [
            'message' => $request->session()->get('form_message') ?? ($form->settings['success_message'] ?? 'Merci !'),
            'thanks' => true,
        ]);
    }

    public function embedScript(Form $form): Response
    {
        $this->ensureActive($form);

        return response($this->embed->script($form), 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
            'Access-Control-Allow-Origin' => '*',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function ensureActive(Form $form): void
    {
        abort_unless($form->isActive(), 404);
    }

    /** @return array<string, mixed> */
    private function data(Form $form): array
    {
        $design = $form->design ?? [];

        return [
            'form' => $form,
            'design' => $this->views->design($design),
            'title' => $design['title'] ?? $form->name,
            'description' => $design['description'] ?? null,
            'button' => $design['button_text'] ?? "S'inscrire",
        ];
    }
}
