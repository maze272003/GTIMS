@php use Carbon\Carbon; @endphp
<div class="fixed w-full h-screen top-0 left-0 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden" id="viewarchivedstocksmodal">
    <div class="modal bg-white rounded-lg w-full max-w-4xl max-h-screen overflow-y-auto p-6">
        <div class="flex items-center justify-between border-b border-gray-200 pb-3 mb-4 sticky top-0 bg-white z-10">
            <p class="text-xl font-medium text-gray-700">Archived Stocks in <span id="archived-product-name" class="font-semibold text-red-500"></span></p>
            <button id="closeviewarchivedstocksmodal" class="p-2 rounded-full hover:bg-gray-100">
                <i class="fa-regular fa-xmark text-xl"></i>
            </button>
        </div>
        
        <div class="overflow-x-auto overflow-y-auto h-fit max-h-[70vh]" id="archived-stock-list"> 
            <table class="w-full text-sm">
                <thead class="bg-gray-200 text-gray-700 uppercase text-xs sticky top-0">
                    <tr>
                        <th class="p-3 text-left">#</th>
                        <th class="p-3 text-left">Batch Number</th>
                        <th class="p-3 text-left">Quantity</th>
                        <th class="p-3 text-center">Expiry Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y" id="archived-stocks-tbody">
                    {{-- JavaScript will populate this --}}
                </tbody>
            </table>
            
            <div id="archive-loader" class="text-center p-4 hidden">
                <i class="fa-regular fa-spinner-third fa-spin text-2xl text-gray-500"></i>
            </div>
        </div>
    </div>
</div>