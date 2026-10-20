<?php

return [
    /*
    | Days after which the stored attachments of transactional messages in a final
    | state are deleted by `transactional:prune-attachments` (docs/45 §45.7.9).
    */
    'attachment_retention_days' => (int) env('TRANSACTIONAL_ATTACHMENT_RETENTION_DAYS', 30),
];
