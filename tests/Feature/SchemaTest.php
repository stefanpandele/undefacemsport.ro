<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * MySQL and MariaDB reject any identifier longer than this. SQLite does not.
 */
const MAX_IDENTIFIER_LENGTH = 64;

test('no index name is too long for MySQL', function () {
    // The suite runs on SQLite, which happily accepts a 68-character index name,
    // so `migrate:fresh` on the MariaDB dev database was the first thing to fail.
    // This measures the names SQLite did keep, which are the ones Laravel would
    // have generated for MySQL too.
    //
    // Renaming a table is what makes this bite: `club_location_sport` fit at 52
    // characters and `organization_location_sport` did not at 68.
    $tooLong = [];

    foreach (Schema::getTableListing() as $table) {
        foreach (Schema::getIndexes($table) as $index) {
            $name = $index['name'];

            if (strlen((string) $name) > MAX_IDENTIFIER_LENGTH) {
                $tooLong[] = $name.' ('.strlen((string) $name).')';
            }
        }
    }

    expect($tooLong)->toBeEmpty(
        'Give these an explicit short name in the migration: '.implode(', ', $tooLong),
    );
});

test('no foreign key name is too long for MySQL', function () {
    // SQLite does not name foreign keys, so the name Laravel would generate for
    // MySQL is reconstructed here: {table}_{column}_foreign.
    $tooLong = [];

    foreach (Schema::getTableListing() as $table) {
        // SQLite qualifies the listing with `main.`, which is not part of the name
        // Laravel would build.
        $bare = Str::afterLast($table, '.');

        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            foreach ($foreignKey['columns'] as $column) {
                $name = $bare.'_'.$column.'_foreign';

                if (strlen($name) > MAX_IDENTIFIER_LENGTH) {
                    $tooLong[] = $name.' ('.strlen($name).')';
                }
            }
        }
    }

    expect($tooLong)->toBeEmpty(
        'Give these an explicit short name in the migration: '.implode(', ', $tooLong),
    );
});
