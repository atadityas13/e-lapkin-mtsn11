# Mobile App Access Logs

File ini berisi log akses untuk aplikasi mobile E-LAPKIN.

Format log:
```json
{
    "timestamp": "YYYY-MM-DD HH:MM:SS",
    "action": "action_name",
    "ip_address": "user_ip",
    "user_agent": "user_agent_string",
    "is_valid_app": true/false,
    "url": "requested_url"
}
```

Log akan otomatis dibuat saat ada akses ke aplikasi mobile.
