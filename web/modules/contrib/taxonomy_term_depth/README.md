CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Api Documentation
 * Maintainers


INTRODUCTION
------------

This module adds crucial property called "depth" which indicates the term depth
with a value 1, 2, 3 and further if needed. It automatically changes taxonomy
term depth on save or update. There is a batch to automatically calculate
and set all term depths if you are installing this on an existing project.

 * For a full description of the module, visit the project page:
   https://www.drupal.org/project/taxonomy_term_depth

 * To submit bug reports and feature suggestions, or to track changes:
   https://www.drupal.org/project/issues/taxonomy_term_depth


REQUIREMENTS
------------

This module requires no modules outside of Drupal core.


INSTALLATION
------------

 * Install the Taxonomy Term Depth module as you would normally install a
   contributed Drupal module. Visit https://www.drupal.org/node/1897420 for
   further information.


API DOCUMENTATION
-----------------

Here is short API documentation of useful functions:

```
taxonomy_term_depth_get_by_tid($tid, $force = FALSE);
``

Gets term's depth value in digital representation
$tid is term id which depth needs to be get
$force (optional) when set to TRUE, the depth will be recalculated again using
SQL queries and database record will be updated with new value. For example, if
you changed its parent using plain SQL update query, you can easily call this
function with $force=true and depth value will be updated in database

```
function taxonomy_term_depth_get_chain($tid, $reveresed = FALSE);
```

Returns an array containing all parents of this term
$tid is the identifier of term whose parent we need to get
$reversed (optional) if set to TRUE the return array will be reversed

Examples:

```
  Term level 1 [tid=1]
  --Term level 2 [tid=2]
  ----Term level 3 [tid=3]

> echo taxonomy_term_depth_get_by_tid(3);
> 3

> echo taxonomy_term_depth_get_by_tid(1):
> 1


> print_r(taxonomy_term_depth_get_parents(1));
> array()

> print_r(taxonomy_term_depth_get_parents(3));
> array(2, 1)

> print_r(taxonomy_term_depth_get_full_chain(1));
> array(1, 2, 3)

> print_r(taxonomy_term_depth_get_full_chain(1, TRUE));
> array(3, 2, 1)
```


MAINTAINERS
-----------

 * Jazin Bazin (Cadila) - https://www.drupal.org/u/cadila
 * Mitesh Patel (miteshmap) - https://www.drupal.org/u/miteshmap

Supporting organizations:

 * Adyax - https://www.drupal.org/adyax
