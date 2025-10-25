<style>
    #box1{
       animation: spin 2s infinite; 
    }
    #box2{
       animation: spin 1.5s infinite; 
    }
    #box3{
       animation: spin 1s infinite; 
    }

    @keyframes spin{
        50% {
        transform: rotate(180deg);
        100%{
            transform: rotate(360deg);
        }
    }
    }
</style>

<div class="fixed top-0 left-0 w-full h-full flex items-center justify-center gap-2 bg-white z-50">
    <div id="box1" class="w-12 h-12 bg-blue-700 rounded-lg "></div>
    <div id="box2" class="w-12 h-12 bg-blue-700 rounded-lg "></div>
    <div id="box3" class="w-12 h-12 bg-blue-700 rounded-lg "></div>
</div>