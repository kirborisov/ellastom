
$(document).ready(function() {

    $('div.date-item, span.go_to_date').click(function() {
       let doctorId = $(this).attr('doctor_id') 
       let date_s = $(this).attr('date_s')

       let curBlock = $(this).closest('div.raspisanie')
       curBlock.find('div.owl-3').hide()
       $(`div.owl-3[doctor_id=${doctorId}][date_s="${date_s}"]`).show()

       curBlock.find('div.date-item').css('background', '#fff')
       blockDate = curBlock.find(`div.date-item[date_s="${date_s}"]`)
       blockDate.css('background','#FFF4D8')

       if($(this).is('span.go_to_date')) {
           var container = curBlock.find('div.date-container');    // Контейнер, который будет прокручиваться

           var scrollPosition = blockDate.offset().left - container.offset().left + container.scrollLeft();
           container.animate({
            scrollLeft: scrollPosition
           }, 'slow');

       }

    })

    /* При клике на кнопку - показ блока записи */
    $('button[start-date-time]').click(function() {
        $('.appointment-box').show()
        $('.overlay').show()
    })

});


// Закрытие блока при клике на крестик
function hideAppointment() {
    appointmentBox.hide();
    appointmentSend.hide();
}

$('.close-button').click(hideAppointment);
$('.overlay').click(hideAppointment);


function hideAppointment() {
    $('.appointment-box').hide()
    $('.appointment-send').hide()
    $('.overlay').hide()
    $('p#bad_code').hide()
    $('p#bad_ip').hide()
    $('input[name=sended_sms]').val('no')
    $('input[name=code_phone]').hide()
    $('input[name=code_phone]').val('')

}

/* При нажатие на кнопку записи */
function generateUUID() {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
    var r = Math.random() * 16 | 0,
        v = c === 'x' ? r : (r & 0x3 | 0x8);
    return v.toString(16);
  });
}

$(document).ready(function() {
    $('button[start-date-time]').click(function() {
        
        let DoctorId = $(this).attr('doctor_id')
        let DateAndTime = $(this).attr('start-date-time')
        let strDateTime = $(this).attr('str-date-time')
        let Id = generateUUID()
        let doctorName = $(this).closest('div.doc-content').find('div.doc-info h2 strong').text()
        
        $('input[name=Id]').val(Id)
        $('input[name=DoctorId]').val(DoctorId)
        $('input[name=DateAndTime]').val(DateAndTime)
        $('span[id=doctorName]').text(doctorName)
        $('span[id=strDateTime]').text(strDateTime)

    })

    function validatePhone(phone) {
        var phoneRegEx = /^\+7\d{10}$/;
        return phoneRegEx.test(phone);
    }

    function validateName(name) {
        return name.trim() !== '';
    }

    $('button[type=make]').click(function() {    
        formData = {}    
        formData.Id = $('input[name=Id]').val()    
        formData.DoctorId = $('input[name=DoctorId]').val()    
        formData.DoctorName = $('div.appointment-box span#doctorName').text()
        formData.DateAndTime = $('input[name=DateAndTime]').val()    
        formData.ClientPhone = $('input[name=ClientPhone]').val()    
        formData.ClientFullName = $('input[name=ClientFullName]').val() 

        formData.sended_sms = $('input[name=sended_sms]').val()
        formData.code_phone = $('input[name=code_phone]').val()

        var isValid = true;

        if (!validateName(formData.ClientFullName)) {
            $('input[name=ClientFullName]').css('border', '1px solid red');
            isValid = false;
        } else {
            $('input[name=ClientFullName]').css('border', '');
        }

        if (!validatePhone(formData.ClientPhone)) {
            $('input[name=ClientPhone]').css('border', '1px solid red');
            isValid = false;
        } else {
            $('input[name=ClientPhone]').css('border', '');
        }

        console.log(isValid, formData.ClientFullName, formData.ClientPhone)
    
        if(isValid == false) return

        if(isValid) {
            $.ajax({    
                url: '/appointment',    
                type: 'POST',    
                data: formData,    
                success: function(response) {    
                    // Обработка успешного ответа от сервера    
                    if( response['type'] == 'sms' ) {
                        $('input#code_phone').show()
                        $('input[name=sended_sms]').val('yes')
                        $('p#bad_code').hide()
                        $('p#bad_ip').hide()
                    } else if(response['type'] == 'bad_code') {
                        $('p#bad_ip').hide()
                        $('p#bad_code').show()
                    } else if(response['type'] == 'bad_ip') {
                        $('p#bad_ip').show()
                    } else {
                        $('div.appointment-box').hide()    
                        $('div.appointment-send').show()    
                        $('p#bad_code').hide()
                        $('input[name=sended_sms]').val('no')
                        $('input[name=code_phone]').hide()
                        $('input[name=code_phone]').val('')

                    }
                    console.log(response);    
                },    
                error: function(xhr, status, error) {    
                    // Обработка ошибки    
                    console.log(error);    
                }    
            });
        }
        
        console.log(formData)
    })

})

$(document).ready(function() {
    $('input[name=ClientPhone]').mask('+79999999999'); // Здесь задается формат маски для номера телефона
});
