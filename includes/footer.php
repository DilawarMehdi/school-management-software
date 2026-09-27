  </main><!-- end page-content -->
</div><!-- end main-wrapper -->

<!-- TOAST NOTIFICATIONS -->
<div id="toastContainer" class="toast-container"></div>

<!-- GLOBAL MODAL -->
<div class="modal-overlay" id="globalModal" style="display:none;">
  <div class="modal-box" id="globalModalBox">
    <div class="modal-header">
      <h3 id="globalModalTitle">Confirm</h3>
      <button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-body" id="globalModalBody"></div>
    <div class="modal-footer" id="globalModalFooter">
      <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
      <button class="btn btn-danger" id="globalModalConfirm">Confirm</button>
    </div>
  </div>
</div>

<script src="<?= BASE_URL ?>js/app.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>



