# Price archive management

System Administrators can open **Archive Management** from the sidebar. Select a
completed year, preview its active record count, review the matching records, and
confirm archiving. The year is determined by the existing `created_at` upload timestamp.
No separate Canvass Date or year field is needed. For example, archiving 2025 selects
records uploaded from January 1, 2025 through December 31, 2025. Older records uploaded
recently belong to their upload year. The existing Excel paste column layouts stay unchanged.

Records stay in the same table. Active and Archived views have category, year, and
text filters. Staff can search, copy, and export archived prices; only administrators
can archive, restore a completed batch, or retry a failed operation. Archived records
and records captured by a pending operation cannot be edited or deleted.

The preview is checked again when submitted. Each archive captures an exact record
membership snapshot. Newly added records are excluded from an existing batch. Jobs
process 500 records per transaction, retaining progress and membership for retry and
restoration. History retains the original administrator name, record count, timestamps,
and restoration administrator. No records are automatically archived or deleted.

## Queue operation

A Laravel database queue worker must run for archive and restore requests to finish:

```sh
php artisan queue:work database --tries=3 --timeout=60
```

The existing `composer run dev` command includes a queue listener when
`QUEUE_CONNECTION=database`. In deployment, supervise and restart the worker after
code updates. The database queue must use the same database connection as the archive
tables so that progress and follow-up jobs commit together. The archive jobs use the
`database` connection explicitly, even if another default queue is configured.

Refresh Archive Management to see updated counts and status. Retry resumes after the
last committed chunk. Finish or retry any unfinished operation before starting a new
archive. Restoration returns that batch to the active view; it does not change original
record IDs, creation timestamps, or duplicate fingerprints. Regular database backups must
include the active records, archive metadata, membership tables, and queued jobs.
