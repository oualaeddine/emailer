<?php

namespace App\Modules\Recipients\Lists\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Recipients\Lists\Contracts\ListMembershipService;
use App\Modules\Recipients\Lists\Http\Requests\ListMembershipRequest;
use App\Modules\Recipients\Lists\Jobs\BulkMembershipJob;
use App\Modules\Recipients\Lists\Support\ListOperationStore;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\RecipientList;
use Illuminate\Http\JsonResponse;

/** docs/45 §45.5.6 — POST /recipient-lists/{id}/contacts. */
class ListMembershipController extends Controller
{
    public function __construct(
        private readonly ListMembershipService $members,
        private readonly ListOperationStore $operations,
        private readonly AuditLogger $audit,
    ) {}

    public function update(ListMembershipRequest $request, string $id): JsonResponse
    {
        $list = RecipientList::query()->where('uuid', $id)->firstOrFail();
        $action = $request->string('action')->toString();
        $selection = $request->input('selection');

        if (! empty($selection)) {
            $ids = $this->members->resolveSelection($selection);
            if (count($ids) > 1000 || isset($selection['all_matching'])) {
                $op = $this->operations->start('contacts_bulk', $request->user(), count($ids));
                BulkMembershipJob::dispatch($list->id, $action, $selection, $op['id']);

                return response()->json(['data' => $this->operations->present($op)], 202);
            }
            $result = $this->apply($list, $action, $ids, []);
        } else {
            $notFound = [];
            $ids = [];
            if (! empty($request->input('recipient_ids'))) {
                $ids = Recipient::query()->whereIn('uuid', $request->input('recipient_ids'))->pluck('id')->all();
            }
            if (! empty($request->input('emails'))) {
                $emails = array_values(array_unique(array_map(fn ($e) => mb_strtolower(trim($e)), $request->input('emails'))));
                $found = Recipient::query()->whereIn('email', $emails)->pluck('id', 'email');
                $ids = array_merge($ids, $found->values()->all());
                $notFound = array_values(array_filter($emails, fn (string $e) => ! $found->has($e)));
            }
            $result = $this->apply($list, $action, array_values(array_unique($ids)), $notFound);
        }

        $this->audit->record('list.contacts_'.($action === 'add' ? 'added' : 'removed'), $list, $request->user(), null, [
            'added' => $result['added'], 'removed' => $result['removed'],
        ]);

        return response()->json(['data' => $result]);
    }

    /**
     * @param  list<int>  $ids
     * @param  list<string>  $notFound
     * @return array{added:int,removed:int,already:int,not_found:list<string>}
     */
    private function apply(RecipientList $list, string $action, array $ids, array $notFound): array
    {
        if ($action === 'add') {
            $added = $this->members->add($list, $ids, 'manual');

            return ['added' => $added, 'removed' => 0, 'already' => count($ids) - $added, 'not_found' => $notFound];
        }

        return ['added' => 0, 'removed' => $this->members->remove($list, $ids, 'manual'), 'already' => 0, 'not_found' => $notFound];
    }
}
