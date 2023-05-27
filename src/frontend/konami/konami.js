var koKeys = [38, 38, 40, 40, 37, 39, 37, 39, 66, 65];
var koNb = 0;
$j(document).keydown(function (e) {
    if (e.keyCode === koKeys[koNb++]) {
        if (koNb === koKeys.length) {
            $j('my-app').hide();
            var img = $j('<img id="konami" style="position: absolute; display: none">'); //Equivalent: $(document.createElement('img'))
            img.attr('src', 'konami.png');
            img.appendTo('body');
            var audio = new Audio('konami.mp3');
            audio.play();
            var konami = $j("#konami");
            konami.css('top', '200px');
            konami.show();
            var pos = 100;
            var rot = 0;
            var id = setInterval(frame, 10);

            function frame() {
                if (pos > 1400) {
                    clearInterval(id);
                    konami.remove();
                    konami.css('left', '200px');
                    $j('my-app').show();
                } else {
                    pos += 5;
                    konami.css('left', pos + 'px');
                    if (pos == 0 || pos == 400) {
                        konami.css({
                            '-webkit-transform': 'rotate(-15deg)',
                            '-moz-transform': 'rotate(-15deg)',
                            '-ms-transform': 'rotate(-15deg)',
                            'transform': 'rotate(-15deg)'
                        });
                    } else if (pos == 200 || pos == 600) {
                        konami.css({
                            '-webkit-transform': 'rotate(15deg)',
                            '-moz-transform': 'rotate((15degg)',
                            '-ms-transform': 'rotate((15deg)',
                            'transform': 'rotate((15deg)'
                        });
                    }
                    if (pos > 800) {
                        rot += 5;
                        konami.css({
                            '-webkit-transform': 'rotate(' + rot + 'deg)',
                            '-moz-transform': 'rotate(' + rot + 'deg)',
                            '-ms-transform': 'rotate(' + rot + 'deg)',
                            'transform': 'rotate(' + rot + 'deg)'
                        });
                    }

                }
            }
            koNb = 0;
        }
    } else {
        koNb = 0;
    }
});