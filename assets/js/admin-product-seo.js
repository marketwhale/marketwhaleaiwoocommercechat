jQuery(document).ready(function($) {
    console.log('MWAI: admin-product-seo.js loaded.');
    const $statusDiv = $('#mwai-seo-generation-status');
    if ($statusDiv.length) {
        $statusDiv.text('MWAI SEO script loaded.').css('color', 'gray');
    }
    const $generateBtn = $('#mwai_generate_seo_content_btn');
    const productId = $generateBtn.data('product_id');

    console.log('MWAI: Generate button found:', $generateBtn.length > 0);
    console.log('MWAI: Product ID found:', productId);

    if ($generateBtn.length && productId) {
        console.log('MWAI: Generate button and Product ID are present. Attaching click handler.');
        $generateBtn.on('click', function(e) {
            e.preventDefault();
            console.log('MWAI: Generate button clicked.');
            const $button = $(this);
            const originalButtonText = $button.text();
            const $statusDiv = $('#mwai-seo-generation-status');
            $statusDiv.text('Generating content...').css('color', 'blue');
            $button.text('Generating...').prop('disabled', true);

            console.log('MWAI: Initiating AJAX request...');
            $.ajax({
                url: MWAI_Product_SEO_Ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'mwai_generate_seo_content',
                    product_id: productId,
                    _wpnonce: MWAI_Product_SEO_Ajax.nonce
                },
                success: function(response) {
                    console.log('MWAI: AJAX Success - Full Response Object:', response);
                    if (response.success) {
                        $statusDiv.text('Content generated successfully!').css('color', 'green');
                        
                        console.log('MWAI: Response data (from response.data):', response.data);

                        const generatedTitle = response.data.title;
                        const generatedSlug = response.data.slug;
                        const generatedDescription = response.data.description;
                        const generatedShortDescription = response.data.short_description;
                        const generatedTags = response.data.tags;

                        console.log('MWAI: Generated Title received:', generatedTitle);
                        console.log('MWAI: Generated Slug received:', generatedSlug);
                        console.log('MWAI: Generated Description received:', generatedDescription ? generatedDescription.substring(0, 100) + '...' : 'empty');
                        console.log('MWAI: Generated Short Description received:', generatedShortDescription ? generatedShortDescription.substring(0, 100) + '...' : 'empty');
                        console.log('MWAI: Generated Tags received:', generatedTags);

                        // Function to simulate typing animation
                        function typeContent($element, content, callback, speed = 20) {
                            let i = 0;
                            const isInputField = $element.is('input, textarea');
                            if (isInputField) {
                                $element.val(''); // Clear existing content for input/textarea
                            } else {
                                $element.empty(); // Clear existing content for other elements
                            }

                            const interval = setInterval(() => {
                                if (i < content.length) {
                                    if (isInputField) {
                                        $element.val($element.val() + content.charAt(i));
                                    } else {
                                        $element.append(content.charAt(i));
                                    }
                                    i++;
                                } else {
                                    clearInterval(interval);
                                    if (callback) callback();
                                }
                            }, speed);
                        }

                        // Function to update editor or textarea with typing animation
                        function updateFieldWithTyping(fieldId, content, fieldName, callback) {
                            console.log(`MWAI: updateFieldWithTyping called for ${fieldName} (ID: ${fieldId}) with content length: ${content ? content.length : 0}`);

                            function tryUpdateEditor(attempts, maxAttempts) {
                                if (attempts >= maxAttempts) {
                                    console.error(`MWAI: Failed to update ${fieldName} after ${maxAttempts} attempts. TinyMCE not ready or field not found.`);
                                    if (callback) callback();
                                    return;
                                }

                                const isTinyMCEDefined = typeof tinymce !== 'undefined';
                                if (isTinyMCEDefined) {
                                    const editor = tinymce.get(fieldId);
                                    if (editor && editor.initialized) {
                                        editor.setContent(''); // Clear before typing
                                        let i = 0;
                                        const speed = 20;
                                        const editorInterval = setInterval(() => {
                                            if (i < content.length) {
                                                editor.execCommand('mceInsertContent', false, content.charAt(i));
                                                i++;
                                            } else {
                                                clearInterval(editorInterval);
                                                if (callback) callback();
                                            }
                                        }, speed);
                                        return;
                                    }
                                }

                                // Fallback to plain textarea if TinyMCE is not defined or not ready
                                const $field = $(`#${fieldId}`);
                                if ($field.length) {
                                    typeContent($field, content, callback);
                                    return;
                                }

                                setTimeout(() => tryUpdateEditor(attempts + 1, maxAttempts), 500);
                            }

                            tryUpdateEditor(0, 10);
                        }

                        // Sequence of updates with typing animation
                        const updateTitle = (callback) => {
                            const $titleField = $('#title');
                            console.log('MWAI: Checking for Title field (#title). Found:', $titleField.length > 0);
                            if ($titleField.length && generatedTitle) {
                                typeContent($titleField, generatedTitle, callback);
                                console.log('MWAI: Title updated with typing animation. Generated Title:', generatedTitle);
                            } else {
                                console.error('MWAI: Title field (#title) not found or generated title is empty.');
                                callback();
                            }
                        };

                        const updateSlug = (callback) => {
                            const $slugField = $('#post_name');
                            if ($slugField.length) {
                                typeContent($slugField, generatedSlug, callback);
                                console.log('MWAI: Slug updated with typing animation.');
                            } else {
                                console.error('MWAI: Slug field (#post_name) not found.');
                                callback();
                            }
                        };

                        const updateDescription = (callback) => {
                            updateFieldWithTyping('content', generatedDescription, 'Description', callback);
                        };

                        const updateShortDescription = (callback) => {
                            updateFieldWithTyping('excerpt', generatedShortDescription, 'Short Description', callback);
                        };

                        const updateTags = (callback) => {
                            const $tagsInput = $('#newtag-product_tag');
                            if ($tagsInput.length && generatedTags && generatedTags.length > 0) {
                                // Clear existing tags (if any) - this is a simplified approach
                                // A more robust solution would involve simulating clicks on 'x' for existing tags.
                                $tagsInput.val(generatedTags.join(', '));
                                $('#product_tag_add_submit').trigger('click'); // Simulate click on 'Add' button
                                console.log('MWAI: Tags updated with:', generatedTags.join(', '));
                            } else if ($tagsInput.length) {
                                $tagsInput.val('');
                                console.log('MWAI: No tags returned or tags input not found. Tags might be cleared on backend.');
                            } else {
                                console.error('MWAI: Tags input (#newtag-product_tag) not found.');
                            }
                            callback(); // Always call callback for tags
                        };

                        // Execute updates in sequence
                        updateTitle(() => {
                            updateSlug(() => {
                                updateDescription(() => {
                                    updateShortDescription(() => {
                                        updateTags(() => {
                                            $statusDiv.text('Content generated and fields updated successfully!').css('color', 'green');
                                            $button.text(originalButtonText).prop('disabled', false);
                                        });
                                    });
                                });
                            });
                        });

                    } else {
                        console.error('MWAI: Backend reported error:', response.data.message);
                        $statusDiv.text('Error: ' + response.data.message).css('color', 'red');
                        $button.text(originalButtonText).prop('disabled', false);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('MWAI: AJAX Network Error:', textStatus, errorThrown, jqXHR.responseText);
                    $statusDiv.text('Network error: ' + textStatus + ' - ' + errorThrown).css('color', 'red');
                    $button.text(originalButtonText).prop('disabled', false);
                }
            });
        });
    } else {
        console.error('MWAI: Generate button or product ID not found.');
    }
});
