from django.shortcuts import render
from django.conf import settings

def home(request):
    context = {
        'widget_public_key': getattr(settings, 'FEEDPLE_WIDGET_PUBLIC_KEY', 'wpk_demo_key'),
    }
    return render(request, 'core/index.html', context)
