<?php

namespace App\Modules\Forms\Services;

use App\Modules\Forms\Events\ContactOptInConfirmed;
use App\Modules\Forms\Models\Form;
use App\Modules\Forms\Models\FormSubmission;
use App\Modules\Recipients\Attributes\Contracts\ContactAttributeService;
use App\Modules\Recipients\Lists\Contracts\ListMembershipService;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\RecipientList;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * docs/45 §45.7.4 — double opt-in confirmation of form submissions. Nothing a
 * pending submission carries touches the contact before this runs: on
 * confirmation each `pending_confirmation` submission of the contact becomes
 * `confirmed`, its values are written (all of them with `update_existing`,
 * otherwise only blank attributes), the contact joins the form's lists,
 * `forms.confirmed_count` grows and ContactOptInConfirmed fires once per form.
 */
class FormConfirmationService
{
    public function __construct(
        private readonly ContactAttributeService $attributes,
        private readonly ListMembershipService $lists,
    ) {}

    /** Idempotent; returns the number of submissions confirmed. */
    public function confirm(int $recipientId): int
    {
        $recipient = Recipient::query()->find($recipientId);
        if ($recipient === null) {
            return 0;
        }

        return DB::transaction(function () use ($recipient): int {
            $submissions = FormSubmission::query()
                ->where('recipient_id', $recipient->id)
                ->where('status', 'pending_confirmation')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $confirmedPerForm = [];
            foreach ($submissions as $submission) {
                $claimed = FormSubmission::query()->whereKey($submission->id)->where('status', 'pending_confirmation')
                    ->update(['status' => 'confirmed', 'confirmed_at' => now()]);
                if ($claimed === 0) {
                    continue;
                }

                /** @var Form|null $form */
                $form = Form::withTrashed()->find($submission->form_id);
                if ($form === null) {
                    continue;
                }

                $this->applyValues($recipient, $form, $submission->data ?? []);

                foreach (RecipientList::query()->whereIn('uuid', $form->list_ids ?? [])->get() as $list) {
                    $this->lists->add($list, [$recipient->id], FormSubmissionService::SOURCE, ['formId' => $form->id]);
                }

                $confirmedPerForm[$form->id] = ($confirmedPerForm[$form->id] ?? 0) + 1;
            }

            foreach ($confirmedPerForm as $formId => $count) {
                Form::withTrashed()->whereKey($formId)->increment('confirmed_count', $count);
                ContactOptInConfirmed::dispatch($recipient->id, $formId);
            }

            return array_sum($confirmedPerForm);
        });
    }

    /** @param  array<string, mixed>  $values */
    private function applyValues(Recipient $recipient, Form $form, array $values): void
    {
        $definitions = $this->attributes->definitions();
        $values = array_filter(
            $values,
            fn ($value, $name): bool => strtoupper((string) $name) !== 'EMAIL' && $definitions->has(strtoupper((string) $name)),
            ARRAY_FILTER_USE_BOTH,
        );

        if (! (($form->settings ?? [])['update_existing'] ?? true)) {
            $recipient->refresh();
            $fields = $recipient->custom_fields ?? [];
            $values = array_filter($values, function ($value, $name) use ($definitions, $recipient, $fields): bool {
                $definition = $definitions->get(strtoupper((string) $name));
                $current = $definition->storage === 'column' && $definition->column !== null
                    ? $recipient->getAttribute($definition->column)
                    : ($fields[$definition->name] ?? null);

                return $current === null || $current === '' || $current === [];
            }, ARRAY_FILTER_USE_BOTH);
        }

        if ($values === []) {
            return;
        }

        try {
            $this->attributes->apply($recipient, $values, FormSubmissionService::SOURCE);
        } catch (ValidationException $e) {
            // Values were validated at submission; a definition changed since. Keep the subscription.
            report($e);
        }
    }
}
