document.addEventListener('DOMContentLoaded', function() {
    // Function to update the thumbnail container height
    function updateThumbnailHeight() {
        var thumbnailImages = document.querySelectorAll('.flex-control-thumbs li img');

        if (thumbnailImages.length > 0) {
            // Get the height of the first image in the list (if not 0)
            var thumbnailHeight = 0;

            // Find the first non-zero thumbnail height (since images may load at different times)
            for (let img of thumbnailImages) {
                if (img.offsetHeight > 0) {
                    thumbnailHeight = img.offsetHeight;
                    break; // Stop once we find the first non-zero height
                }
            }

            // If no valid height found, exit early
            if (thumbnailHeight === 0) {
                console.error('Thumbnail height is zero. Cannot calculate maxHeight.');
                return;
            }

            // Calculate the maxHeight for the thumbnail container (4 images + 3 gaps of 10px)
            var maxHeight = (thumbnailHeight * 4) + (10 * 6);  // 4 images + 3 gaps of 10px
            console.log("Calculated Max Height: ", maxHeight); // Debugging log

            // Set max-height for .flex-control-thumbs
            var thumbnailContainer = document.querySelector('.flex-control-thumbs');
            if (thumbnailContainer) {
                thumbnailContainer.style.maxHeight = maxHeight + 'px';
                thumbnailContainer.style.overflowY = 'auto';  // Make sure scrolling is enabled
                console.log("Thumbnail container max-height set to: ", maxHeight);
            }
        } else {
            console.error('No thumbnail images found!');
        }
    }

    // Wait for all images to load, then trigger the update
    function waitForImages() {
        var images = document.querySelectorAll('.flex-control-thumbs li img');
        var totalImages = images.length;
        var imagesLoaded = 0;

        // Check if images are already loaded or if they need to be loaded
        images.forEach(function(img) {
            if (img.complete) {
                imagesLoaded++;
            } else {
                img.onload = function() {
                    imagesLoaded++;
                    if (imagesLoaded === totalImages) {
                        updateThumbnailHeight();
                    }
                };
            }
        });

        // If all images are already loaded, trigger the height calculation
        if (imagesLoaded === totalImages) {
            updateThumbnailHeight();
        }
    }

    // Initial page load trigger (to account for all content and lazy-loaded images)
    waitForImages();

    // Trigger update after a slight delay to ensure images are fully loaded and settled
    setTimeout(function() {
        waitForImages();  // Re-run the image check after the page load
    }, 500);  // Adjust the delay time (500ms) to your needs

    // Listen for swatch selection changes (both color and size)
    function listenForSwatchChange() {
        // Listen for color swatch selection
        var colorSwatches = document.querySelectorAll('.cfvsw-swatches-container.cfvsw-product-container .cfvsw-swatches-option');
        
        colorSwatches.forEach(function(swatch) {
            swatch.addEventListener('click', function() {
                // After selecting a swatch, trigger height update with a delay
                setTimeout(function() {
                    console.log("Color Swatch Selected");
                    updateThumbnailHeight();
                }, 200);  // Wait 200ms before updating
            });
        });

        // Listen for size swatch selection
        var sizeSwatches = document.querySelectorAll('.cfvsw-swatches-container.cfvsw-product-container .cfvsw-swatches-option.cfvsw-label-option');
        
        sizeSwatches.forEach(function(swatch) {
            swatch.addEventListener('click', function() {
                // After selecting a swatch, trigger height update with a delay
                setTimeout(function() {
                    console.log("Size Swatch Selected");
                    updateThumbnailHeight();
                }, 200);  // Wait 200ms before updating
            });
        });
    }

    // Listen for changes when the page is fully loaded
    listenForSwatchChange();

    // Listen for WooCommerce variation changes to force the height update
    document.addEventListener('found_variation', function() {
        // Trigger the height update with a 200ms delay after variation is found
        setTimeout(function() {
            console.log("Variation Found, Updating Thumbnails");
            updateThumbnailHeight();
        }, 200);
    });

    // Listen for WooCommerce variation change event (for when user selects a new variation)
    document.addEventListener('woocommerce_variation_has_changed', function() {
        // Trigger the height update with a 200ms delay after variation has changed
        setTimeout(function() {
            console.log("Variation Changed, Updating Thumbnails");
            updateThumbnailHeight();
        }, 200);
    });

    // Add resize event listener with debouncing to prevent excessive calls
    let resizeTimeout;
    window.addEventListener('resize', function() {
        // Clear the previous timeout
        clearTimeout(resizeTimeout);

        // Set a new timeout (debounced)
        resizeTimeout = setTimeout(function() {
            console.log("Window Resized, Updating Thumbnails");
            updateThumbnailHeight();
        }, 200);  // Adjust the delay to your needs (200ms)
    });
});
