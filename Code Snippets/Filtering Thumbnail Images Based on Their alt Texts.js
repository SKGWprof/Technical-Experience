document.addEventListener('DOMContentLoaded', () => {
    // 1. CONFIGURATION: Change this selector to match your product card container class on the archive page.
    const productCards = document.querySelectorAll('.product'); // <-- ADJUST THIS TO YOUR ARCHIVE PRODUCT WRAPPER CLASS

    /**
     * Helper function to escape special characters in a string for use in a RegExp constructor.
     * @param {string} string - The string to escape.
     * @returns {string} The escaped string.
     */
    function escapeRegExp(string) {
        return string.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\$&');
    }

    /**
     * Filters the product gallery images within a specific product card.
     * Hides thumbnails that do not match all currently selected swatch titles.
     * Updates the main (big) image to the first matching thumbnail.
     * @param {HTMLElement} card - The product card element.
     */
    function filterGalleryImages(card) {
        if (!card) return;

        // 2. ELEMENT SELECTION: Find gallery and swatches inside this product card
        const gallery = card.querySelector('.woocommerce-product-gallery');
        const swatchContainers = card.querySelectorAll('.cfvsw-swatches-container');

        if (!gallery || swatchContainers.length === 0) return;

        const bigImage = gallery.querySelector('.woocommerce-product-gallery__wrapper img');
        const bigImageContainer = bigImage?.closest('.woocommerce-product-gallery__image');
        const thumbnailImages = gallery.querySelectorAll('.flex-control-nav.flex-control-thumbs img');
        const thumbnailItems = gallery.querySelectorAll('.flex-control-nav.flex-control-thumbs li');

        if (!bigImage || !bigImageContainer || thumbnailItems.length === 0) {
            return;
        }

        // Hide all thumbnails initially to prepare for filtering
        thumbnailItems.forEach(item => {
            item.style.display = 'none';
        });

        // 3. COLLECT SELECTED SWATCH TITLES
        const selectedTitles = [];
        swatchContainers.forEach(container => {
            const selected = container.querySelector('.cfvsw-selected-swatch');
            if (selected && selected.dataset.title) {
                // Collect the title data attribute, trim, and convert to lowercase
                selectedTitles.push(selected.dataset.title.trim().toLowerCase());
            }
        });

        let matched = false;
        let firstMatchingThumb = null;

        // 4. FILTER THUMBNAILS
        // Filter thumbnails based on matching ALL selected titles as full phrases or words in the 'alt' attribute
        thumbnailImages.forEach(thumb => {
            const alt = thumb.alt.trim().toLowerCase();
            const thumbnailItem = thumb.closest('li');

            // Check if the thumbnail's alt text matches ALL of the selected swatch titles
            const matchesAll = selectedTitles.every(title => {
                const escapedTitle = escapeRegExp(title);
                // Create a regular expression to match the title as a whole word/phrase
                // surrounded by word boundaries (start/end of string or whitespace)
                const regex = new RegExp(`(?:^|\\s)${escapedTitle}(?:\\s|$)`, 'i');
                return regex.test(alt);
            });

            if (matchesAll) {
                thumbnailItem.style.display = 'block'; // Show matching thumbnail
                if (!firstMatchingThumb) firstMatchingThumb = thumb; // Store the first match
                matched = true;
            } else {
                thumbnailItem.style.display = 'none'; // Hide non-matching thumbnail
            }
        });

        // 5. UPDATE BIG IMAGE
        if (matched && firstMatchingThumb) {
            // Update the main image source to the first matching thumbnail's source
            bigImage.src = firstMatchingThumb.src;
            bigImageContainer.style.display = 'block'; // Ensure the main image container is visible
        } else {
            // Hide the main image if no gallery image matches the selected swatches
            bigImageContainer.style.display = 'none';
        }
    }

    // 6. INITIAL RUN & EVENT LISTENERS
    productCards.forEach(card => {
        // Run initial filtering when the page loads
        filterGalleryImages(card);

        // Listen for clicks on any swatch within this product card
        const swatchContainers = card.querySelectorAll('.cfvsw-swatches-container');
        swatchContainers.forEach(container => {
            container.addEventListener('click', () => {
                // Use a short timeout to allow the swatch plugin to update the 'cfvsw-selected-swatch' class first
                setTimeout(() => filterGalleryImages(card), 100);
            });
        });
    });

    // 7. Dynamic Loading Note:
    // Optional: If your archive loads products dynamically (e.g., infinite scroll), 
    // you will need to implement a MutationObserver or event delegation 
    // to reapply this logic on new product card nodes.
});
