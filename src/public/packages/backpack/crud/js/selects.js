let refreshOptionList = function (element, $field, $refreshUrl) {
    return new Promise(function (resolve, reject) {
        $.ajax({
            url: $refreshUrl,
            data: {
                'field': $field
            },
            type: 'GET',
            // async: false,
            success: function (result) {
                //console.log(result);
                $(element).attr('data-options-for-select', JSON.stringify(result));
                resolve(result);
            },
            error: function (result) {
                // Show an alert with the result

                reject(result);
            }
        });
    });
};


function fillSelectOptions(element, $created = false, $multiple = false) {

    var $options = JSON.parse(element.attr('data-options-for-select'));

    var $allows_null = element.attr('data-allows-null');

    var $relatedKey = element.attr('data-on-the-fly-related-key');

    //used to check if after a related creation the created entity is still available in options
    var $createdIsOnOptions = false;

    //if this field is a select multiple we json parse the current value
    if ($multiple == true) {

        var $currentValue = JSON.parse(element.attr('data-current-value'));

        var selectedOptions = [];

        //if there are any selected options we re-select them
        for (const [key, value] of Object.entries($currentValue)) {
        selectedOptions.push(key);
    }

    //we add the options to the select and check if we have some created, if yes we append to selected options
    for (const [key, value] of Object.entries($options)) {
        var $option = new Option(value, key);

        if ($created) {
            if(key == $created[$relatedKey]) {
                $createdIsOnOptions = true;
                selectedOptions.push(key);
            }
        }

        $(element).append($option);
    }

    $(element).val(selectedOptions);

    }else{
        //it's a single select
        var $currentValue = element.attr('data-current-value');

        for (const [key, value] of Object.entries($options)) {

            var $option = new Option(value, key);
            $(element).append($option);
            if (key == $currentValue) {
                $(element).val(key);
            }
            if ($created) {
                //we check if created is presented in the available options, might not be based on some model constrain (like active() scope)
                if(key == $created[$relatedKey]) {
                    $createdIsOnOptions = true;
                    $(element).val(key);
                }

         }
    }
    }
    if ($allows_null == 'true' && $multiple == false && ($currentValue == '' || (Array.isArray($currentValue) && $currentValue.length)) && $createdIsOnOptions == false) {
        var $option = new Option('-', '');
        $(element).prepend($option);
        if (($currentValue == '' || (Array.isArray($currentValue) && $currentValue.length))) {
            $(element).val('');
        }
    }
    if($allows_null == 'false' && ($currentValue == '' || (Array.isArray($currentValue) && $currentValue.length)) && $createdIsOnOptions == false) {
        $(element).val(Object.keys($options)[0]);

    }

    $(element).trigger('change')
}

function triggerSelectOptions(element, $refreshUrl, $created = false, $multiple = false) {
    $fieldName = element.attr('data-original-name');
    $(element).empty();

    if ($created) {
        refreshOptionList(element, $fieldName, $refreshUrl).then(result => {

            fillSelectOptions(element, $created, $multiple);
        }, result => {

        });
    } else {
        fillSelectOptions(element, $created, $multiple);
    }
}

function setupOnTheFlyButtons(element, urls) {
    var $onTheFlyCreateButton = element.attr('data-on-the-fly-create-button');
    var $fieldEntity = element.attr('data-field-related-name');
    var $onTheFlyCreateButtonElement = $(document.getElementById($onTheFlyCreateButton));

    $onTheFlyCreateButtonElement.on('click', function () {
        $(".loading_modal_dialog").show();
        $.ajax({
            url: urls.createUrl,
            data: {
                'entity': $fieldEntity
            },
            type: 'GET',
            success: function (result) {
                $('body').append(result);
                triggerModal(element, urls);

            },
            error: function (result) {
                // Show an alert with the result
                swal({
                    title: "error",
                    text: "error",
                    icon: "error",
                    timer: 4000,
                    buttons: false,
                });
            }
        });
    });

}

function triggerModal(element, $urls) {
    var $fieldName = element.attr('data-field-related-name');
    var $multiple = (element.attr('data-field-multiple') === 'true');
    var modalName = '#'+$fieldName+'-on-the-fly-create-dialog';
    var $modal = $(modalName);

    $modal.modal({ backdrop: 'static', keyboard: false });
    var $modalSaveButton = $modal.find('#saveButton');
    var $form = $(document.getElementById($fieldName+"-on-the-fly-create-form"));


    initializeFieldsWithJavascript($form);

    $modalSaveButton.on('click', function () {
        var $formData = new FormData(document.getElementById($fieldName+"-on-the-fly-create-form"));

        var loadingText = '<i class="fa fa-circle-o-notch fa-spin"></i> loading...';
        if ($modalSaveButton.html() !== loadingText) {
            $modalSaveButton.data('original-text', $(this).html());
            $modalSaveButton.html(loadingText);
            $modalSaveButton.prop('disabled', true);
        }


        $.ajax({
            url: $urls['createUrl'],
            data: $formData,
            processData: false,
            contentType: false,
            type: 'POST',
            success: function (result) {

                $createdEntity = result.data;
                triggerSelectOptions(element, $urls['refreshUrl'], $createdEntity,$multiple);

                $modal.modal('hide');
                swal({
                    title: "Related entity creation",
                    text: "Related entity created with success.",
                    icon: "success",
                    timer: 4000,
                    buttons: false,
                });
            },
            error: function (result) {
                // Show an alert with the result

                var $errors = result.responseJSON.errors;

                let message = '';
                for (var i in $errors) {
                    message += $errors[i] + ' \n';
                }

                swal({
                    title: "Creating related entity error",
                    text: message,
                    icon: "error",
                    timer: 4000,
                    buttons: false,
                });
                $modalSaveButton.prop('disabled', false);
                $modalSaveButton.html($modalSaveButton.data('original-text'));
            }
        });
    });

    $modal.on('hidden.bs.modal', function (e) {
        $modal.remove();
    });

    $modal.on('shown.bs.modal', function (e) {
        $(".loading_modal_dialog").hide();
    });
}
