/*Tooltip*/
(function($) {
    $(document).ready(function() {
        // Add tooltip container to body if it doesn't exist
        if (!$('.awp-tooltip').length) {
            $('body').append('<div class="awp-tooltip" style="display: none;"></div>');
        }

        // Handle hover events
        $('.thumbnail-stats').hover(
            function(e) { // mouseenter
                var thumbnails = JSON.parse($(this).attr('data-thumbnails'));
                var tooltipContent = generateTooltipContent(thumbnails);

                $('.awp-tooltip')
                    .html(tooltipContent)
                    .css({
                        position: 'absolute',
                        top: e.pageY + 10,
                        left: e.pageX + 10
                    })
                    .show();
            },
            function() { // mouseleave
                $('.awp-tooltip').hide();
            }
        );

        function generateTooltipContent(thumbnails) {
            var content = '';
            thumbnails.forEach(function(thumb) {
                content += '<div class="thumb-stats">';
                content += '<span class="size">' + thumb.size + '</span>';
                content += '<div class="progress-wrapper">';
                content += '<div class="progress-bar" style="width: ' + thumb.percent + '%"></div>';
                content += '</div>';
                content += '<span class="percent">' + thumb.percent + '%</span>';
                content += '</div>';
            });
            return content;
        }
    });
})(jQuery);

/* Start optimization link*/
jQuery(document).ready(function($) {
    $('#optimization-tab').click(function(e) {
        e.preventDefault();
        $('.tab-content').hide();

        $('.nav-tab').removeClass('nav-tab-active');
        $('a[href="#optimization"]').addClass('nav-tab-active');

        var target = $(this).attr('href');
        $(target).show();
    });    
});

/* Setting Tabs */
jQuery(document).ready(function($) {
    // Check the URL fragment to determine the initial tab, default to #general
    var initialTab = window.location.hash || '#general';

    // Set up tabs and content sections
    $('.nav-tab').removeClass('nav-tab-active');
    $('.tab-content').hide();

    // Activate the initial tab based on the URL fragment
    var $initialTab = $(`.nav-tab[href="${initialTab}"]`);
    if ($initialTab.length) {
        $initialTab.addClass('nav-tab-active');
        $(initialTab).show();
    } else {
        // If hash is invalid, default to #general
        $('.nav-tab[href="#general"]').addClass('nav-tab-active');
        $('#general').show();
    }

    // Show or hide the submit button based on the initial tab
    toggleSubmitButton(initialTab);

    // Event listener for tab clicks
    $('.nav-tab').on('click', function(event) {
        event.preventDefault();

        // Update active tab styling
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        // Hide all tab content sections
        $('.tab-content').hide();

        // Show the target tab content
        var target = $(this).attr('href');
        $(target).show();

        // Update the URL hash without causing the jump
        history.replaceState(null, null, target);

        // Hide or show the submit button based on the opened tab
        toggleSubmitButton(target);
    });

    function toggleSubmitButton(tab) {
        if (tab === '#general' || tab === '#advanced') {
            $('#submit').show();
        } else {
            $('#submit').hide();
        }
    }
});


/*Batch Image Optimization*/
jQuery(document).ready(function($) {
    const startButton = $('#start-optimization-button');
    const progressBar = $('#progress-bar');
    const progressText = $('#progress-text');
    const resultsList = $('#optimization-results');
    const progressContainer = $('#progress-container');
    const spinner = $('.progress-status');
    const reOptimizeImages = $('#re-optimize-images');

    let isOptimizing = false;
    let totalOptimized = 0;
    let totalErrors = 0;

    let processed_count = 0;
    let total_unoptimized_count = 0;

    startButton.on('click', function(event) {
        event.preventDefault();

        if (isOptimizing) {
            return;
        }

        // Reset state
        isOptimizing = true;
        totalOptimized = 0;
        totalErrors = 0;
        startButton.prop('disabled', true);
        reOptimizeImages.prop('disabled', true);
        progressContainer.show();
        resultsList.empty().show();
        spinner.show();

        processNextBatch();
    });

    function updateProgress(progress, message) {
        progressBar.css('width', progress + '%');
        progressText.text(message);
    }

    function addResultMessage(result) {
        const statusClass = result.status === 'success' ? 'success' : 'error';
        const messageHtml = `
            <div class="optimization-result ${statusClass}">
                <span class="dashicons dashicons-${result.status === 'success' ? 'yes' : 'no'}"></span>
                ID ${result.id}: ${result.message}
            </div>
        `;
        resultsList.append(messageHtml);
        resultsList.scrollTop(resultsList[0].scrollHeight);
    }

    function processNextBatch() {
        var isReOptimize = reOptimizeImages.is(':checked') ? 1 : 0;
        $.ajax({
            url: wpeio_data.ajaxUrl,
            type: 'POST',
            data: {
                action: 'start_optimization',
                nonce: wpeio_data.nonce,
                processed_count: processed_count,
                total_unoptimized_count: total_unoptimized_count,
                is_re_optimize: isReOptimize
            },
            success: function(response) {
                if (!response.success) {
                    handleError('Server returned an error: ' + (response.data?.message || 'Unknown error'));
                    return;
                }

                const data = response.data;

                // Check for "invalid api key" in the results message
                if (data.results && data.results.message) {
                    const message = data.results.message;
                    const regex = /invalid api key/i;

                    if (regex.test(message)) {
                        handleError('Invalid API key detected. Please check your API key.');
                        return; // Stop further processing if invalid API key is found
                    }
                }

                // Process results
                if (data.results && data.results.length > 0) {
                    processed_count =  data.processed_count + 1;
                    total_unoptimized_count =  data.total_unoptimized_count;

                    data.results.forEach(result => {
                        if (result.status === 'success') {
                            totalOptimized++;
                        } else {
                            totalErrors++;
                        }
                        addResultMessage(result);
                    });
                }

                // Update progress message
                const progressMessage = `Optimized: ${totalOptimized} | Errors: ${totalErrors} | Progress: ${data.progress}%`;
                updateProgress(data.progress, progressMessage);

                // Continue if not complete
                if (!data.is_complete) {
                    // Add a small delay between batches
                    setTimeout(processNextBatch);
                } else {
                    finishOptimization(data.message || `Optimization complete! Successfully optimized ${totalOptimized} images with ${totalErrors} errors.`);
                }
            },
            error: function(xhr, status, error) {
                handleError('Ajax request failed: ' + error);
            }
        });
    }

    function handleError(message) {
        isOptimizing = false;
        startButton.prop('disabled', false);
        reOptimizeImages.prop('disabled', false);
        updateProgress(0, 'Error: ' + message);
        spinner.hide();
        addResultMessage({
            id: 'system',
            status: 'error',
            message: message
        });
    }

    function finishOptimization(message) {
        isOptimizing = false;
        startButton.prop('disabled', false);
        reOptimizeImages.prop('disabled', false);
        updateProgress(100, message);
        spinner.hide();
    }
});


/*Single image optimization and restore on media listing page*/
(function($) {
    'use strict';

    $(document).ready(function() {
        function handleOptimizationAction($button, action) {
            spinner_enable($button);
            const $container = $button.closest('.optimization-controls');
            const attachmentId = $container.data('id');

            if ($container.hasClass('processing')) {
                return;
            }

            $container.addClass('processing');
            $button.prop('disabled', true);
            var is_re_optimize = $button.hasClass('reoptimize-image') ? 1 : 0;

            $.ajax({
                url: wpeio_data.ajaxUrl,
                type: 'POST',
                data: {
                    action: action,
                    attachment_id: attachmentId,
                    nonce: wpeio_data.nonce,
                    is_re_optimize: is_re_optimize
                },
                success: function(response) {
                    if (response.success) {
                        // Reload the page to show updated status
                        window.location.reload();
                    } else {
                        spinner_disable();
                        alert(response.data.message || wpeio_data.i18n.error);
                    }
                },
                error: function() {
                    alert(wpeio_data.i18n.networkError);
                    spinner_disable();
                },
                complete: function() {
                    spinner_disable();
                    $container.removeClass('processing');
                    $button.prop('disabled', false);
                }
            });
        }

        // Optimize image button handler
        $(document).on('click', '.optimization-controls .optimize-image', function(e) {
            e.preventDefault();
            handleOptimizationAction($(this), 'optimize_single_image');
        });

        // Restore image button handler
        $(document).on('click', '.optimization-controls .restore-image', function(e) {
            e.preventDefault();
            handleOptimizationAction($(this), 'restore_single_image');
        });

        // ReOptimize image button handler
        $(document).on('click', '.optimization-controls .reoptimize-image', function(e) {
            e.preventDefault();
            handleOptimizationAction($(this), 'optimize_single_image');
        });
    });

    function spinner_enable($button) {
        $button.children('.spinner').first().css({
            display: 'inline-block'
        });
    }

    function spinner_disable() {
        $('.optimization-controls .spinner').hide();
    }
})(jQuery);



/*Stats*/
jQuery(document).ready(function($) {});


/*Bulk Restore images*/
jQuery(document).ready(function($) {
    const restoreButton = $('#start-restore-button');
    const restoreProgressBar = $('#restore-progress-bar');
    const restoreProgressText = $('#restore-progress-text');
    const restoreResultsList = $('#restore-results');
    const restoreProgressContainer = $('#restore-progress-container');
    const restoreSpinner = $('#restore-progress-container .progress-status');

    let isRestoring = false;
    let totalRestored = 0;
    let totalErrors = 0;
    let initialTotal = 0;

    restoreButton.on('click', function(event) {
        event.preventDefault();

        if (isRestoring) {
            return;
        }

        // Initialize restore process
        initializeRestore();
    });

    function initializeRestore() {
        $.ajax({
            url: wpeio_data.ajaxUrl,
            type: 'POST',
            data: {
                action: 'init_bulk_restore',
                nonce: wpeio_data.nonce
            },
            success: function(response) {
                if (!response.success) {
                    handleRestoreError('Failed to initialize restore process');
                    return;
                }

                initialTotal = response.data.total_images;

                // Reset state
                isRestoring = true;
                totalRestored = 0;
                totalErrors = 0;
                restoreButton.prop('disabled', true);
                restoreProgressContainer.show();
                restoreResultsList.empty().show();
                restoreSpinner.show();

                // Start processing
                processNextRestoreBatch();
            },
            error: function(xhr, status, error) {
                handleRestoreError('Failed to initialize restore process: ' + error);
            }
        });
    }

    function updateRestoreProgress(progress, message) {
        restoreProgressBar.css('width', progress + '%');
        restoreProgressText.text(message);
    }

    function addRestoreResult(result) {
        const statusClass = result.status === 'success' ? 'success' : 'error';
        const messageHtml = `
            <div class="optimization-result ${statusClass}">
                <span class="dashicons dashicons-${result.status === 'success' ? 'yes' : 'no'}"></span>
                ID ${result.id}: ${result.message}
            </div>
        `;
        restoreResultsList.append(messageHtml);
        restoreResultsList.scrollTop(restoreResultsList[0].scrollHeight);
    }

    function processNextRestoreBatch() {
        $.ajax({
            url: wpeio_data.ajaxUrl,
            type: 'POST',
            data: {
                action: 'start_bulk_restore',
                nonce: wpeio_data.nonce
            },
            success: function(response) {
                if (!response.success) {
                    handleRestoreError('Server returned an error: ' +
                        (response.data?.message || 'Unknown error'));
                    return;
                }

                const data = response.data;

                // Process results
                if (data.results && data.results.length > 0) {
                    data.results.forEach(result => {
                        if (result.status === 'success') {
                            totalRestored++;
                        } else {
                            totalErrors++;
                        }
                        addRestoreResult(result);
                    });
                }

                // Update progress message
                const progressMessage =
                    `Restored: ${data.restored_count} of ${data.initial_total} | Errors: ${totalErrors} | Progress: ${data.progress}%`;
                updateRestoreProgress(data.progress, progressMessage);

                // Continue if not complete
                if (!data.is_complete) {
                    setTimeout(processNextRestoreBatch);
                } else {
                    finishRestore(
                        `Restore complete! Successfully restored ${data.restored_count} images with ${totalErrors} errors.`
                    );
                }
            },
            error: function(xhr, status, error) {
                handleRestoreError('Ajax request failed: ' + error);
            }
        });
    }

    function handleRestoreError(message) {
        isRestoring = false;
        restoreButton.prop('disabled', false);
        updateRestoreProgress(0, 'Error: ' + message);
        restoreSpinner.hide();
        addRestoreResult({
            id: 'system',
            status: 'error',
            message: message
        });
    }

    function finishRestore(message) {
        isRestoring = false;
        restoreButton.prop('disabled', false);
        updateRestoreProgress(100, message);
        restoreSpinner.hide();
    }
});