<!DOCTYPE html>
<html lang="en">

<head>
    @include('frontend.includes.head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@algolia/autocomplete-theme-classic">
    <style>


        .upload-form {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            width: 100%;
        }

        .upload-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        .upload-form input[type="file"] {
            display: block;
            margin-bottom: 16px;
        }

        .upload-form button {
            background-color: #007bff;
            color: #fff;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        .upload-form button:hover {
            background-color: #0056b3;
        }
    </style>
</head>

<body>
    <!-- main-section starts -->
    <div class="wrapper .gray-bg-100">
        <!-- header start here -->
        <header id="mainHeader" class="main-header inner-header">
            @include('frontend.includes.header')
        </header>
        <main class="main-content">
            <!-- contect page start -->
            <section class="store-details banner-pad pb-0 mt-5 mb-5">
                <div class="container-fluid mt-5 mb-5"style="padding: 0px 30px;">
                    <div class="row">
                        <div id="colId1" class="col-12" style="display: flex; justify-content: center;">
                            <form class="upload-form" id="uploadForm" action="/handle-upload" method="POST" enctype="multipart/form-data">
                                @csrf
                                <input type="hidden" name="selected_value" value="6">
                                <label for="file">Choose Csv file (Drag & Drop):</label>
                                <input type="file" id="file" name="file" accept=".csv" required>
                                <button type="submit">Upload</button>
                            </form>
                        </div>
                        <div id="colId2" class="col-12" style="display: none;">
                            <h2 id="message">Upload Results</h2>
                            <div id="results" class="d-flex justify-content-around mt-3">
                                <div>
                                    <h3>Saved Images</h3>
                                    <ul id="savedImages"></ul>
                                </div>
                                <div>
                                    <h3>Existing Images</h3>
                                    <ul id="existingImages"></ul>
                                </div>
                                <div>
                                    <h3>Failed Images</h3>
                                    <ul id="failedImages"></ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            @include('frontend.includes.footer')
            <!-- main footer end -->
        </main>
    </div>
</body>
</html>

<script>
        document.getElementById('uploadForm').addEventListener('submit', function(event) {
            event.preventDefault();
            var formData = new FormData(this);
            
            fetch(this.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                }
            })
            .then(response => response.json())
            .then(data => {
                var messageElement = document.getElementById('message');
                messageElement.innerText = data.message + " ";
                var iconElement = document.createElement('i');
                iconElement.className = 'fa fa-check';
                iconElement.style.fontSize = '28px';
                iconElement.style.color = 'green';
                messageElement.appendChild(iconElement);
                document.getElementById('colId1').className = 'col-4';
                document.getElementById('colId2').style.display = 'block';
                document.getElementById('colId2').className = 'col-8 p-3 text-primary-emphasis bg-primary-subtle border border-primary-subtle rounded-3';
                var savedImages = document.getElementById('savedImages');
                savedImages.innerHTML = '';
                data.savedImages.forEach(function(image, index) {
                    var li = document.createElement('li');
                    li.innerText = (index + 1) + '. ' + image;
                    savedImages.appendChild(li);
                });
                
                var existingImages = document.getElementById('existingImages');
                existingImages.innerHTML = '';
                data.existingImages.forEach(function(image, index) {
                    var li = document.createElement('li');
                    li.innerText = (index + 1) + '. ' + image;
                    existingImages.appendChild(li);
                });

                var failedImages = document.getElementById('failedImages');
                failedImages.innerHTML = '';
                data.failedImages.forEach(function(image, index) {
                    var li = document.createElement('li');
                    li.innerText = (index + 1) + '. ' + image;
                    failedImages.appendChild(li);
                });
            })
            .catch(error => console.error('Error:', error));
        });
    </script>