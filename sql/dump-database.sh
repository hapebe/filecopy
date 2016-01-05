#!/bin/bash
mysqldump --databases filecopy --quote-names --no-data --user=filecopy --password=? > filecopy-structure.sql
mysqldump --databases filecopy --quote-names --disable-keys --delayed-insert --complete-insert --skip-extended-insert --user=filecopy --password=? | bzcat -z -1 > filecopy-complete-dump.sql.bz2
