#!/bin/bash
# Reload services so renewed certificates are used.
systemctl reload apache2
systemctl restart proftpd
