from django.conf.urls.defaults import *

urlpatterns = patterns('',
    (r'^$', 'bswt.views.default'),
    (r'^index\.html$', 'bswt.views.default'),
    (r'^hello\.html$', 'bswt.views.helloworld'),
)
