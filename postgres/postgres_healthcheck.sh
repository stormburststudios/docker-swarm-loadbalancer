#!/bin/bash
PG_PASSWORD=${POSTGRES_PASSWORD} pg_isready -U "${POSTGRES_USER}"
