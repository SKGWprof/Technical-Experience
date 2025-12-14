document.addEventListener('DOMContentLoaded', function () {

    if (window.innerWidth < 1024 && document.body.classList.contains('single-product')) {

        let allImages = [];



        function initializeImages() {

            const productImages = document.querySelectorAll('.woocommerce-product-gallery img');

            productImages.forEach(image => {

                allImages.push({

                    src: image.src,

                    alt: image.alt.toLowerCase(),

                    imgHTML: image.outerHTML

                });

            });

        }



        function filterVariantImages() {

            const allSwatchContainers = document.querySelectorAll('.cfvsw-swatches-container');

            const galleryWrapper = document.querySelector('.woocommerce-product-gallery__wrapper');

            let selectedTitles = [];



            allSwatchContainers.forEach(container => {

                const selected = container.querySelector('.cfvsw-selected-swatch');

                if (selected && selected.dataset.title) {

                    const title = selected.dataset.title.trim().toLowerCase();

                    selectedTitles.push(title);

                }

            });



            if (!galleryWrapper) return;



            galleryWrapper.style.display = 'none';

            let matchingImagesCount = 0;

            let newGalleryHTML = '';



            allImages.forEach(imageData => {

                const matchesAll = selectedTitles.every(title => {

                    const regex = new RegExp(`\\b${title}\\b`, 'i');

                    return regex.test(imageData.alt);

                });



                if (matchesAll) {

                    newGalleryHTML += `<div class="woocommerce-product-gallery__image">${imageData.imgHTML}</div>`;

                    matchingImagesCount++;

                }

            });



            galleryWrapper.innerHTML = newGalleryHTML;



            setTimeout(() => {

                styleGalleryWrapper();

                resetTranslateValue();

                reinitializeWooGallery();

                galleryWrapper.style.display = 'flex';

            }, 200);

        }



        function styleGalleryWrapper() {

            const galleryWrapper = document.querySelector('.woocommerce-product-gallery__wrapper');

            const flexViewport = document.querySelector('.flex-viewport');

            if (!galleryWrapper) return;



            const images = galleryWrapper.querySelectorAll('.woocommerce-product-gallery__image');

            let imageHeight = 0;



            galleryWrapper.style.display = 'flex';

            galleryWrapper.style.flexWrap = 'nowrap';

            galleryWrapper.style.overflow = 'hidden';



            images.forEach((imageWrapper, index) => {

                const img = imageWrapper.querySelector('img');

                imageWrapper.style.flex = '0 0 auto';

                imageWrapper.style.width = `${window.innerWidth}px`;

                imageWrapper.style.margin = '0';

                imageWrapper.style.padding = '0';

                imageWrapper.style.display = 'block';



                if (img) {

                    img.style.width = '100%';

                    img.style.height = 'auto';

                    img.style.objectFit = 'cover';



                    if (index === 0) {

                        if (img.complete) {

                            imageHeight = img.offsetHeight;

                            galleryWrapper.style.minHeight = `${imageHeight}px`;

                            if (flexViewport) flexViewport.style.minHeight = `${imageHeight}px`;

                        } else {

                            img.onload = () => {

                                imageHeight = img.offsetHeight;

                                galleryWrapper.style.minHeight = `${imageHeight}px`;

                                if (flexViewport) flexViewport.style.minHeight = `${imageHeight}px`;

                            };

                        }

                    }

                }

            });

        }



        function resetTranslateValue() {

            const galleryWrapper = document.querySelector('.woocommerce-product-gallery__wrapper');

            if (galleryWrapper) {

                galleryWrapper.style.transform = 'translate3d(0px, 0px, 0px)';

            }

        }



        function clamp(value, min, max) {

            return Math.min(Math.max(value, min), max);

        }



        function getMaxNegativeTranslateX() {

            const galleryWrapper = document.querySelector('.woocommerce-product-gallery__wrapper');

            if (!galleryWrapper) return 0;



            let totalWidth = 0;

            const images = galleryWrapper.querySelectorAll('.woocommerce-product-gallery__image');

            images.forEach(imgWrap => {

                totalWidth += imgWrap.offsetWidth;

            });



            const viewportWidth = window.innerWidth;

            const maxNegative = viewportWidth - totalWidth;

            return maxNegative < 0 ? maxNegative : 0;

        }



        function clampTransform() {

            const galleryWrapper = document.querySelector('.woocommerce-product-gallery__wrapper');

            if (!galleryWrapper) return;



            const style = galleryWrapper.style.transform;

            const regex = /translate3d\((-?\d+(?:\.\d+)?)px,\s*(-?\d+(?:\.\d+)?)px,\s*(-?\d+(?:\.\d+)?)px\)/;

            const match = style.match(regex);



            if (!match) return;



            let currentX = parseFloat(match[1]);

            const currentY = parseFloat(match[2]);

            const currentZ = parseFloat(match[3]);



            const maxNegative = getMaxNegativeTranslateX();

            const clampedX = clamp(currentX, maxNegative, 0);



            if (clampedX !== currentX) {

                galleryWrapper.style.transform = `translate3d(${clampedX}px, ${currentY}px, ${currentZ}px)`;



                const $gallery = jQuery('.woocommerce-product-gallery');

                if ($gallery.length && $gallery.data('flexslider')) {

                    const slider = $gallery.data('flexslider');



                    const slideWidth = slider.slides.first().outerWidth(true);

                    let slideIndex = Math.round(-clampedX / slideWidth);

                    slideIndex = clamp(slideIndex, 0, slider.count - 1);



                    if (typeof slider.flexAnimate === 'function') {

                        slider.flexAnimate(slideIndex, true);

                    } else {

                        slider.currentSlide = slideIndex;

                        slider.animatingTo = slideIndex;

                    }

                }

            }

        }



        function startClampingObserver() {

            const galleryWrapper = document.querySelector('.woocommerce-product-gallery__wrapper');

            if (!galleryWrapper) return;



            const observer = new MutationObserver(() => {

                clampTransform();

            });



            observer.observe(galleryWrapper, { attributes: true, attributeFilter: ['style'] });

        }



        function reinitializeWooGallery() {

            const $gallery = jQuery('.woocommerce-product-gallery');



            if ($gallery.length && typeof $gallery.flexslider === 'function') {

                $gallery.flexslider({

                    animation: "slide",

                    controlNav: false,

                    animationLoop: false,

                    slideshow: false,

                    smoothHeight: true,

                    directionNav: true,

                    prevText: "",

                    nextText: "",

                    start: function(slider) {

                        startClampingObserver();

                    },

                    before: function(slider) {

                        if (slider.animating) return;



                        if (slider.animatingTo < 0) {

                            slider.animatingTo = 0;

                            return false;

                        }

                        if (slider.animatingTo >= slider.count) {

                            slider.animatingTo = slider.count - 1;

                            return false;

                        }

                    }

                });

            }

        }



        function attachSwatchChangeListener() {

            const allSwatchContainers = document.querySelectorAll('.cfvsw-swatches-container');

            allSwatchContainers.forEach(container => {

                container.addEventListener('click', () => {

                    setTimeout(filterVariantImages, 100);

                });

            });

        }



        initializeImages();

        filterVariantImages();

        attachSwatchChangeListener();

    }

});
