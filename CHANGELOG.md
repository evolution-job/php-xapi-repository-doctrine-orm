CHANGELOG
=========

0.7
-----

* add ORM storage for xAPI Profile documents
* exclude voided Statements from Statement list queries while preserving
  recursive StatementRef matching
* persist State update timestamps and filter State queries by `since`
* persist the Content-Type of State documents

0.6
-----

* include recursively referencing statements in non-time filtered queries
* add an index for StatementRef lookups
* filter by `stored` instead of `created`
* add indexes for stored-time ordering and statement actor, activity, and
  inverse-functional identifier filters
* add ORM repository for canonical Verb lookups

0.5
-----

* dropped doctrine QuoteStrategy service and fix columns names
* minimal Symfony packages version bumped to 7.4

0.4
-----

* dropped support for PHP < 8.4
* added unit and functional `StateRepositoryTest`

0.3
-----

* dropped support for `doctrine/orm` < 3.0

0.2
-----

* dropped support for PHP < 8.1
* add QuoteStrategy service
* Replace "json_array" by "json" into metadata 
* All dependencies from php-xapi/* are now loaded from forks at `https://github.com/evolution-job/`

0.1.0
-----

First release providing common functions for Doctrine ORM based xAPI learning
record store backends.
