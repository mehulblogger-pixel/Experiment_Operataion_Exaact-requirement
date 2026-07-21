from django.urls import path

from . import views

app_name = "operations"

urlpatterns = [
    path("board/", views.schedule_board, name="schedule_board"),
    path("calls/", views.call_list, name="call_list"),
    path("calls/new/", views.call_create, name="call_create"),
    path("calls/<int:pk>/", views.call_detail, name="call_detail"),
    path("calls/<int:pk>/allocate/", views.allocate_inspector, name="allocate"),
    path("calls/<int:pk>/reject/", views.reject_call, name="reject"),
    path("calls/<int:pk>/complete/", views.complete_call, name="complete"),
    path("reports-pending/", views.reports_pending, name="reports_pending"),
    path("deliverables/<int:pk>/submit/", views.deliverable_submit, name="deliverable_submit"),
    path("invoice-pending/", views.invoice_pending, name="invoice_pending"),
    path("my-work/", views.my_work, name="my_work"),
]
