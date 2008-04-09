from django.template import Context, loader
from django.shortcuts import render_to_response
from django.http import HttpResponse

def default (request):
  return render_to_response('index.html', {})

def helloworld (request):
  t = loader.get_template('hello.html')
  c = Context({
      'extra': 'This is django stuff.',
    })
  return HttpResponse(t.render(c))

