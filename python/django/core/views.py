from django.shortcuts import render
from django.conf import settings
from core.models import Organization, Ticket

def home(request):
    # Auto-seed sample database records if empty
    try:
        if Organization.objects.count() == 0:
            org = Organization.objects.create(name="Acme Corp", plan="Enterprise")
            Ticket.objects.create(organization=org, subject="Setup Feedple AI Widget", status="closed", priority="high")
            Ticket.objects.create(organization=org, subject="Sync Customer DB Schema", status="open", priority="medium")
    except Exception:
        pass

    context = {
        'widget_public_key': getattr(settings, 'FEEDPLE_WIDGET_PUBLIC_KEY', 'wpk_demo_key'),
    }
    return render(request, 'core/index.html', context)
