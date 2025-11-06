#!/bin/bash
#
# Script d'optimisation des pictogrammes locaux
# Redimensionne toutes les images PNG à 256x256 pixels pour accélérer le rendu PDF
#
# Prérequis: ImageMagick (sudo apt install imagemagick)
#

set -e

PICTO_DIR="$(dirname "$0")/../public/uploads/pictograms"

if [ ! -d "$PICTO_DIR" ]; then
    echo "❌ Répertoire $PICTO_DIR introuvable"
    exit 1
fi

# Vérifier que mogrify est installé
if ! command -v mogrify &> /dev/null; then
    echo "❌ ImageMagick n'est pas installé"
    echo "   Installez-le avec: sudo apt install imagemagick"
    exit 1
fi

echo "🖼️  Optimisation des pictogrammes..."
echo "📁 Répertoire: $PICTO_DIR"
echo ""

# Compter le nombre d'images
COUNT=$(find "$PICTO_DIR" -type f \( -iname "*.png" -o -iname "*.jpg" -o -iname "*.jpeg" \) | wc -l)

if [ "$COUNT" -eq 0 ]; then
    echo "ℹ️  Aucune image à optimiser"
    exit 0
fi

echo "📊 Images trouvées: $COUNT"
echo ""

# Créer un backup (optionnel)
read -p "🔹 Créer un backup avant optimisation ? (y/N) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    BACKUP_DIR="$PICTO_DIR/../pictograms_backup_$(date +%Y%m%d_%H%M%S)"
    mkdir -p "$BACKUP_DIR"
    cp -r "$PICTO_DIR"/* "$BACKUP_DIR/"
    echo "✅ Backup créé: $BACKUP_DIR"
    echo ""
fi

# Optimiser toutes les images PNG/JPG
echo "⚙️  Redimensionnement en cours..."
cd "$PICTO_DIR"

# PNG
if ls *.png 1> /dev/null 2>&1; then
    mogrify -resize 256x256\> -quality 85 -strip *.png
fi

# JPG
if ls *.jpg 1> /dev/null 2>&1 || ls *.jpeg 1> /dev/null 2>&1; then
    mogrify -resize 256x256\> -quality 85 -strip *.jpg *.jpeg 2>/dev/null || true
fi

echo ""
echo "✅ Optimisation terminée!"
echo ""
echo "💡 Les pictogrammes sont maintenant redimensionnés à 256x256px maximum"
echo "   Cela accélère considérablement la génération des PDF."
