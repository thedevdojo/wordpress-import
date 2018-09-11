@extends('voyager::master')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-data"></i> Wordpress Importer <small style="margin-left:15px; position:relative;">Import your Wordpress XML export into the Voyager Database</small>
        </h1>
    </div>
@stop

@section('content')

    <style>
        label{
            font-size:16px;
            font-weight:500;
        }
        label i{
            position:relative;
            top:2px;
        }
        .panel-bordered>.panel-body{
            overflow:visible;
        }
    </style>

    <div class="page-content browse container-fluid">
        @include('voyager::alerts')
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-bordered">
                    <div class="panel-body table-responsive">
                        <p>Upload your Wordpress XML export file below and click on Import</p>
                        <hr>

                        <form method="POST" action="{{ route('voyager.dashboard') . '/wordpress-import' }}" enctype="multipart/form-data">
                            <label for="copyimages" data-toggle="tooltip" title="Featured images for posts and pages will be copied over to your storage. If you select 'No' the image references will remain the same and no images will be copied." data-placement="right">Copy Images? <i class="voyager-info-circled"></i></label><br>
                            <input type="checkbox" name="copyimages" class="toggleswitch"
                                data-on="Yes" checked="checked"
                                data-off="No"><br>
                            <hr>

                            <label for="timeout" data-toggle="tooltip" title="When copying over posts and images from your site it may take awhile if you have a lot of data, set this to as high as you would like to prevent the script from timing out." data-placement="right">Max Timeout in Seconds <i class="voyager-info-circled"></i></label><br>
                            <input type="text" name="timeout" class="form-control" value="900" >
                            <hr>    


                            <label for="wpexport" data-toggle="tooltip" title="Inside of your Wordpress Admin you can chose to export data by visiting Tools->Export." data-placement="right">Wordpress XML file</label><br>
                            <input type="file" name="wpexport">
                            <hr>
                            <input type="hidden" name="_token" value="{{ csrf_token() }}">
                            <button class="btn btn-primary">Import</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('javascript')

    <script>
        $('document').ready(function(){
            $('.toggleswitch').bootstrapToggle();
            $('[data-toggle="tooltip"]').tooltip(); 
        });
    </script>

@endsection