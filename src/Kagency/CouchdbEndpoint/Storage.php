<?php

namespace Kagency\CouchdbEndpoint;

class Storage
{
    /**
     * data = array()
     *
     * @var array
     */
    private $data = array();

    /**
     * Updates
     *
     * @var int
     */
    private $updates = array();

    /**
     * Synced revisions
     *
     * @var array
     */
    private $syncedRevisions = array();

    /**
     * Revision differ
     *
     * @var RevisionDiffer
     */
    private $revisionDiffer;

    /**
     * Conflict decider
     *
     * @var ConflictDecider
     */
    private $conflictDecider;

    /**
     * Revision calculator
     *
     * @var RevisionCalculator
     */
    private $revisionCalculator;

    /**
     * __construct
     *
     * @param RevisionDiffer $revisionDiffer
     * @param ConflictDecider $conflictDecider
     * @return void
     */
    public function __construct(RevisionDiffer $revisionDiffer, ConflictDecider $conflictDecider, RevisionCalculator $revisionCalculator)
    {
        $this->revisionDiffer = $revisionDiffer;
        $this->conflictDecider = $conflictDecider;
        $this->revisionCalculator = $revisionCalculator;
    }

    /**
     * Get document count
     *
     * @return void
     */
    public function getDocumentCount()
    {
        return count($this->data);
    }

    /**
     * Get update sequence
     *
     * @return void
     */
    public function getUpdateSequence()
    {
        if (!count($this->updates)) {
            return 0;
        }

        return max(array_keys($this->updates));
    }

    /**
     * Get document
     *
     * @param string $document
     * @return mixed
     */
    public function getDocument($document)
    {
        if (!isset($this->data[$document])) {
            throw new \OutOfBoundsException("No document with ID $document");
        }

        $document = $this->data[$document];
        unset($document['_conflict']);
        return $document;
    }

    /**
     * Store documents
     *
     * @param array $documents
     * @return void
     */
    public function storeDocuments(array $documents)
    {
        foreach ($documents as $document) {
            $this->storeDocument($document);
        }
    }

    /**
     * storeDocument
     *
     * @param array $document
     * @return void
     */
    protected function storeDocument(array $document)
    {
        $documentId = $document['_id'];
        if (isset($this->data[$documentId])) {
            $document = $this->conflictDecider->select($document, $this->data[$documentId]);
        }

        $this->data[$document['_id']] = $document;

        $sequence = count($this->updates) + 1;
        $this->updates[$sequence] = array(
            'id' => $document['_id'],
            'sequence' => $sequence,
            'revision' => $document['_rev'],
        );
    }

    /**
     * Update documents
     *
     * @param array $documents
     * @return void
     */
    public function updateDocuments(array $documents)
    {
        return null;
    }

    /**
     * Get changes
     *
     * @param string $since
     * @return Storage\Update[]
     */
    public function getChanges($since)
    {
        $changes = array();
        $sequenceMap = array();

        foreach ($this->updates as $update) {
            if ($update['sequence'] <= $since) {
                continue;
            }

            $changes[] = $change = new Storage\Update(
                $update['sequence'],
                $update['id'],
                array(
                    array(
                        'rev' => $update['revision'],
                    ),
                )
            );

            if (isset($this->data[$update['id']]) &&
                isset($this->data[$update['id']]['_conflict'])) {
                // @TODO: Not sure about the order here, but it works for now
                array_unshift(
                    $change->changes,
                    array(
                        'rev' => $this->data[$update['id']]['_conflict'],
                    )
                );
            }

            $sequenceMap[$update['id']][] = $update['sequence'];
        }

        // Filter changes, we do not need. Only replicate the last
        // change for every document.
        $sequenceMap = array_map(
            function ($sequences) {
                return array_slice($sequences, 0, -1);
            },
            $sequenceMap
        );

        return array_values(
            array_filter(
                $changes,
                function ($change) use ($sequenceMap) {
                    return !in_array(
                        $change->seq,
                        $sequenceMap[$change->id]
                    );
                }
            )
        );
    }

    /**
     * Calculate revision diff
     *
     * @param array $existingRevisions
     * @return array
     */
    public function calculateRevisionDiff(array $existingRevisions)
    {
        $missingRevisions = array();
        foreach ($existingRevisions as $id => $revisions) {
            $missingRevisions[$id] = $this->revisionDiffer->calculate(
                $revisions,
                isset($this->data[$id]) ? $this->data[$id]['_rev'] : null
            );
        }

        return array_filter($missingRevisions);
    }

    /**
     * Get synced change
     *
     * @param array $revision
     * @return void
     */
    public function getSyncedChange($revision)
    {
        if (!isset($this->syncedRevisions[$revision])) {
            throw new \OutOfBoundsException("Revision $revision not synchronized.");
        }

        $revisionDocument = $this->syncedRevisions[$revision];
        unset($revisionDocument['_revisions']);
        return $revisionDocument;
    }

    /**
     * Store synced change
     *
     * @param array $revisionDocument
     * @return void
     */
    public function storeSyncedChange(array $revisionDocument)
    {
        $id = substr($revisionDocument['_id'], strpos($revisionDocument['_id'], '/') + 1);
        $revisionDocument['_rev'] = $this->revisionCalculator->getNextRevision($revisionDocument);
        $this->syncedRevisions[$id] = $revisionDocument;
    }
}
