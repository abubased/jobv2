<!DOCTYPE html>
<html lang="bn">
<body style="font-family:sans-serif; padding:24px; text-align:center;">
    <textarea id="copy-text" readonly style="width:100%; max-width:400px; height:100px;">{{ $text }}</textarea><br><br>
    <button onclick="copyText()" style="padding:10px 20px;">কপি করুন</button>
    <p id="copied-msg" style="display:none; color:green;">কপি হয়েছে ✓</p>

    <script>
        function copyText() {
            const textarea = document.getElementById('copy-text');
            textarea.select();
            navigator.clipboard.writeText(textarea.value).then(() => {
                document.getElementById('copied-msg').style.display = 'block';
            });
        }
    </script>
</body>
</html>
