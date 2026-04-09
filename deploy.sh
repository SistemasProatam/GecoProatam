#!/bin/bash

# Configurar git
git config user.email "ci@proatam.com"
git config user.name "GitLab CI"

# Leer versión actual
VERSION=$(grep "APP_VERSION" version.php | grep -oP "\d+\.\d+\.\d+")
MAJOR=$(echo $VERSION | cut -d. -f1)
MINOR=$(echo $VERSION | cut -d. -f2)
PATCH=$(echo $VERSION | cut -d. -f3)

# Leer tipo de commit
COMMIT_MSG=$(git log -1 --pretty=%s)
COMMIT_TYPE=$(echo $COMMIT_MSG | cut -d: -f1 | tr '[:upper:]' '[:lower:]')

echo "Commit tipo: $COMMIT_TYPE"
echo "Versión actual: $VERSION"

# Incrementar según tipo
if [ "$COMMIT_TYPE" = "feat" ]; then
    MINOR=$((MINOR + 1))
    PATCH=0
    echo "Incrementando MINOR"
elif echo "$COMMIT_TYPE" | grep -qE "^(fix|security|config|refactor|docs)$"; then
    PATCH=$((PATCH + 1))
    echo "Incrementando PATCH"
else
    echo "Tipo no reconocido, versión sin cambios"
    exit 0
fi

# Nueva versión
NEW_VERSION="$MAJOR.$MINOR.$PATCH"
TODAY=$(date +%d/%m/%Y)

# Actualizar version.php
sed -i "s/APP_VERSION', '[^']*'/APP_VERSION', '$NEW_VERSION'/" version.php
sed -i "s|APP_UPDATE', '[^']*'|APP_UPDATE', '$TODAY'|" version.php

echo "Versión actualizada a $NEW_VERSION"

# Subir cambios de vuelta a GitLab
git add version.php
git commit -m "version: $NEW_VERSION [skip ci]"
git push origin main

echo "version.php actualizado en GitLab"