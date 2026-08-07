from django.db import models

class Organization(models.Model):
    name = models.CharField(max_length=255)
    plan = models.CharField(max_length=100, default="Pro")
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        db_table = "organizations"

    def __str__(self):
        return self.name

class Ticket(models.Model):
    organization = models.ForeignKey(Organization, on_delete=models.CASCADE, related_name="tickets")
    subject = models.CharField(max_length=255)
    status = models.CharField(max_length=50, default="open")
    priority = models.CharField(max_length=50, default="medium")
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        db_table = "tickets"

    def __str__(self):
        return self.subject
