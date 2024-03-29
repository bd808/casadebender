#!/usr/bin/env python
# Google App Engine + Django WSGI handler
#
# $Id$

import logging, os, sys

# Google App Engine imports.
from google.appengine.ext.webapp import util

# Force sys.path to have our own directory first, in case we want to import
# from it.
sys.path.insert(0, os.path.abspath(os.path.dirname(__file__)))

# Force Django to reload its settings.
from django.conf import settings
settings._target = None

# Must set this env var *before* importing any part of Django
os.environ['DJANGO_SETTINGS_MODULE'] = 'settings'

# Import the part of Django we need.
import django.core.handlers.wsgi
import django.core.signals
import django.db
import django.dispatch.dispatcher

def log_exception (*args, **kwds):
  logging.exception('Exceptionin request:')

# Log errors
django.dispatch.dispatcher.connect(
    log_exception,
    django.core.signals.got_request_exception)

# Unregister the rollback event handler
django.dispatch.dispatcher.disconnect(
    django.db._rollback_on_exception,
    django.core.signals.got_request_exception)
 
def main():
  # Create a Django application for WSGI.
  application = django.core.handlers.wsgi.WSGIHandler()

  # Run the WSGI CGI handler with that application.
  util.run_wsgi_app(application)

if __name__ == '__main__':
  main()
