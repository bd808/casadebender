#!/usr/bin/env python
# -*- coding: utf-8 -*-
#
# Copyright (c) 2008-2010, Bryan Davis
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without 
# modification, are permitted provided that the following conditions are met:
#     - Redistributions of source code must retain the above copyright notice, 
#     this list of conditions and the following disclaimer.
#     - Redistributions in binary form must reproduce the above copyright 
#     notice, this list of conditions and the following disclaimer in the 
#     documentation and/or other materials provided with the distribution.
#
# THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" 
# AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE 
# IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE 
# ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE 
# LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR 
# CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF 
# SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS 
# INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN 
# CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) 
# ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE 
# POSSIBILITY OF SUCH DAMAGE.

"""
Simple log file parser. Useful for extracting interesting fields from the mass
of log data.
"""

import collections
import re


FORMAT = (r'^(?P<datetime>\S+) \[(?P<pid>\d+)\] (?P<level>\S+) '
          r'(?P<context>\S+) (?P<mdc>(\S+=\S+ )*)?- (?P<message>.*)')
RE_FORMAT = re.compile(FORMAT, re.DOTALL)

TIMESTAMP = r'\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[-+]\d{2}:\d{2}'

IGNORE_ITEM = '~~logscan.ignore.item~~'


def extract_whole_line (lines, pat=TIMESTAMP):
  """
  Generator to collect a whole logging entry.

  A whole entry is the data starting with /pat/ up to (but not including) the
  next line starting with /pat/.

  Args:
    lines: line generator
    pat: re pattern that indicates the start of a new entry
  """
  patc = re.compile(pat)
  buf = lines.next()
  while 1:
    peek = lines.next()
    if patc.match(peek):
      yield buf
      buf = peek
    else:
      buf = buf + peek
#end extract_whole_line


def field_map (dictseq, name, func):
  """
  Process a sequence of dictionaries and remap one of the fields

  Typically used in a generator chain to coerce the datatype of a particular
  field. eg ``log = field_map(log, 'status', int)``

  Args:
    dictseq: Sequence of dictionaries
    name: Field to modify
    func: Modification to apply
  """
  for d in dictseq:
    if name in d:
      d[name] = func(d[name])
    yield d
#end field_map


def field_expand (dictseq, name, func):
  """
  Process a sequence of dictionaries and expand one of the fields

  Typically used in a generator chain to split a filed into one or more
  additional fields. eg ``log = field_map(log, 'mdc', split_mdc)``

  Args:
    dictseq: Sequence of dictionaries
    name: Field to modify
    func: Modification to apply
  """
  for d in dictseq:
    if name in d:
      d.update(func(d[name]))
    yield d
#end field_expand


def split_mdc (mdc):
  """
  split a `key=value key=value ...` MDC log field set into a dictionary of
  key=value pairs.

  Args:
    mdc: MDC field

  Returns:
    dict of key=value pairs
  """
  mdc = dict(('mdc_' + p).split('=') for p in mdc.split())
  mdc['mdc_keys'] = mdc.keys()
  return mdc
#end split_mdc


def parse_log (lines, logpat=RE_FORMAT):
  """
  Parse a log file into a sequence of dictionaries

  Args:
    lines: line generator
    logpat: regex to split lines

  Returns:
    generator of mapped lines
  """
  groups = (logpat.match(line) for line in extract_whole_line(lines))
  tuples = (g.groupdict() for g in groups if g)
  log = field_expand(tuples, 'mdc', split_mdc)
  return log
#end parse_log


def filter_exact (log, field, match):
  """
  Filter that only emits records with field that matches given text

  Args:
    log: log generator
    field: field to match
    match: value to match in field
  """
  for r in log:
    if field in r and r[field] == match:
      yield r
#end filter_exact


def filter_regex (log, field, exp):
  """
  Filter that only emits records with field that matches given regex

  Match is a search, so if you want to anchor the pattern to the start of the
  field contents, use the ``^`` char.

  Args:
    log: log generator
    field: field to match
    exp: compiled regex to match on field
  """
  for r in log:
    if field in r and exp.search(r[field]):
      yield r
#end filter_regex


def print_fields (log, want, seperator=' '):
  """
  Print selected fields from a log.

  Args:
    log: log generator
    want: list of fields to print
    seperator: char to separate entries with
  """
  try:
    for r in log:
      fields = [r[f].strip() for f in want if f in r]
      print seperator.join(fields)
  except IOError, e:
    # should only happen when output is run through head or somethign similar
    # that closes stdout when it doesn't want any more data
    return
#end print_fields


def date_sort (a, b):
  """Compare two log messages for sort order based on their datetime info"""
  return cmp(a['datetime'], b['datetime']);
#end date_sort


def print_report (log, expect):
  """
  Print a report to stdout based on the given log generator and expected
  message configuration.

  If the regex for a given label includes named pattern captures those named
  captures can be used alter the label for a particular match. For example:
  >>> e = { 'found %(val)s': re.compile(r'something (?P<val>\d+)'), }
  
  The special label defined in the logscan.IGNORE_ITEM constant can be used to
  silently discard lines that are not desired to be reported as an occurance
  count or an unexpected entry.

  The generated report will have a block of label: count pairs at the top
  followed by pretty printed versions of any log entries that were found but
  not expected.

  Args:
    log: log generator
    expect: map of label: regex pairs
  """
  found = collections.defaultdict(int)
  extra = []

  for r in log:
    unexpected = True

    for slot, pattern in expect.iteritems():
      m = pattern.match(r['message'])
      if m:
        # grab named matches from pattern match
        replace_keys = m.groupdict()
        # merge in raw log data so keys can use it
        replace_keys.update(r)
        # increment counter named by applying found tokens to slot
        found[slot % replace_keys] += 1
        unexpected = False
        break;
    #end for

    if unexpected and 'DEBUG' != r['level'] and 'INFO' != r['level']:
      # ignore debug and info messages, too noisy
      extra.append(r)
  #end for

  print "%-50s : %7s" % ("error", "count")
  print "=" * 60
  keys = found.keys()
  keys.sort()
  for item in keys:
    if item == IGNORE_ITEM: continue
    print "%-50s : %7d" % (item, found[item])
  print

  # sort remaining messages by date
  extra.sort(date_sort)

  import textwrap
  wrapit = textwrap.TextWrapper(initial_indent='  ', subsequent_indent='    ')
  for r in extra:
    print "%(datetime)s %(level)s" % r ,
    if 'mdc' in r:
      print "%(context)s -" % r
      for k in r['mdc_keys']:
        print "    %s=[%s]" % (k, r[k])
    else:
      print "%(context)s -" % r

    print wrapit.fill(r['message'])
    print

#end print_report


if __name__ == '__main__':
  """simple command line tool to extract named fields from a log on stdin."""
  import optparse
  parser = optparse.OptionParser(usage="usage: %prog [options] < example.log",
      version="%prog $Revision$")

  capture_groups = RE_FORMAT.groupindex.items()
  capture_groups.sort(key=lambda x: x[1])
  capture_groups = [x[0] for x in capture_groups]
  parser.add_option("-f", "--fields", dest="fields", 
      help="list of fields to display: %s" % ', '.join(capture_groups),
      metavar="FIELD1,FIELD2,...",
      default=','.join(capture_groups))

  parser.add_option("-m", "--match", dest="m_filters",
      help="field name and string to match in messages",
      metavar="FIELD=MATCH_TEXT",
      action="append"
      )
  parser.add_option("-r", "--regex", dest="re_filters",
      help="field name and pattern to search for in messages",
      metavar="FIELD=PATTERN",
      action="append"
      )
  parser.add_option("-s", "--seperator", dest="seperator",
      help="seperator to place between output fields",
      default=' ')

  (options, args) = parser.parse_args()

  import sys
  log = parse_log(sys.stdin)

  # exact match filters
  if options.m_filters:
    for m in options.m_filters:
      field, text = m.split('=', 1)
      log = filter_exact(log, field, text)

  # re filters
  if options.re_filters:
    for m in options.re_filters:
      field, pat = m.split('=', 1)
      log = filter_regex(log, field, re.compile(pat, re.DOTALL))

  print_fields(log, options.fields.split(','), options.seperator)
